<?php

namespace App\Modules\Api\V1\Organization\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\Branch\Models\Branch;
use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\Organization\Requests\StoreOrganizationRequest;
use App\Modules\Api\V1\Organization\Requests\UpdateOrganizationRequest;
use App\Modules\Api\V1\Organization\Resources\OrganizationResource;
use App\Modules\Api\V1\User\Models\User;
use App\Services\DefaultStaffProfilesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OrganizationController extends Controller
{
    public function store(StoreOrganizationRequest $request)
    {
        $values = $request->input('data.values');

        $result = DB::transaction(function () use ($values) {
            $organization = Organization::create([
                'name' => $values['name'],
                'description' => $values['description'] ?? null,
                'email' => $values['email'] ?? null,
                'phone' => $values['phone'] ?? null,
                'address' => $values['address'] ?? null,
            ]);

            $mainBranch = Branch::create([
                'organization_id' => $organization->id,
                'name' => 'Main',
                'type' => 'warehouse',
                'address' => $values['address'] ?? null,
                'phone' => $values['phone'] ?? null,
            ]);

            $userData = $values['firstUser'];
            $user = User::create([
                'organization_id' => $organization->id,
                'branch_id' => $mainBranch->id,
                'first_name' => $userData['firstName'],
                'last_name' => $userData['lastName'],
                'email' => $userData['email'],
                'phone' => $userData['phoneNumber'] ?? null,
                'role' => 'admin',
                'is_active' => 1,
                'password' => Hash::make($userData['password']),
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            app(DefaultStaffProfilesService::class)->ensureForOrganization(
                (string) $organization->id,
                (string) $user->id
            );

            return [
                'organization' => $organization,
                'user' => $user,
                'token' => $token,
                'org_id' => $organization->id,
                'main_branch' => $mainBranch,
                'refresh_token' => null,
            ];
        });

        $user = $result['user'];
        $mainBranch = $result['main_branch'];

        return $this->success([
            'token' => $result['token'],
            'refresh_token' => null,
            'org_id' => $result['org_id'],
            'branches' => [[
                'id' => $mainBranch->id,
                'org_id' => $result['org_id'],
                'name' => $mainBranch->name,
                'address' => $mainBranch->address,
                'phone' => $mainBranch->phone,
                'type' => $mainBranch->type,
            ]],
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                'email' => $user->email,
                'phone_number' => $user->phone,
                'role' => 'admin',
                'is_admin' => true,
                'is_active' => 1,
                'org_id' => $result['org_id'],
                'branch_id' => $mainBranch->id,
                'organization' => [
                    'id' => $result['organization']->id,
                    'name' => $result['organization']->name,
                ],
                'allowed_modules' => $user->getAllowedModules(),
            ],
            'organization' => $result['organization'],
        ], 'Organization created successfully.', 201);
    }

    public function show($id)
    {
        try {
            $organization = Organization::findOrFail($id);
            $resource = new OrganizationResource($organization);

            $fieldList = \App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig::getApiFieldsForView('Organization', 'DetailView');

            return $this->success([
                'fields' => $fieldList,
                'values' => $resource->toArray(request()),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Organization not found.', null, null, null, 404);
        }
    }

    public function update(UpdateOrganizationRequest $request, $id)
    {
        try {
            $organization = Organization::findOrFail($id);
            $values = $request->input('data.values');

            $organization->update([
                'name' => $values['name'],
                'description' => $values['description'] ?? null,
                'email' => $values['email'] ?? null,
                'phone' => $values['phone'] ?? null,
                'address' => $values['address'] ?? null,
            ]);

            return $this->success(new OrganizationResource($organization));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Organization not found.', null, null, null, 404);
        }
    }

    public function destroy($id)
    {
        try {
            $organization = Organization::findOrFail($id);
            $organization->delete();

            return $this->success(null, 'Organization successfully deleted.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Organization not found.', null, null, null, 404);
        }
    }

    public function search(Request $request)
    {
        $query = $request->query('query');
        $perPage = $request->query('per_page', 20);

        $results = Organization::where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->paginate($perPage);

        $fieldList = \App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig::getApiFieldsForView('Organization', 'DetailView');

        return $this->paginated(OrganizationResource::collection($results)->resource, $fieldList);
    }
}
