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
use App\Support\ApiPagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OrganizationController extends Controller
{
    public function store(StoreOrganizationRequest $request)
    {
        if (! config('app.allow_public_registration')) {
            return $this->error(
                'Public organization registration is disabled. Contact your administrator.',
                null,
                null,
                null,
                403
            );
        }

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

            app(DefaultStaffProfilesService::class)->ensureForOrganization(
                (string) $organization->id,
                (string) $user->id
            );

            return [
                'organization' => $organization,
                'user' => $user,
                'org_id' => $organization->id,
                'main_branch' => $mainBranch,
            ];
        });

        $user = $result['user'];
        $mainBranch = $result['main_branch'];

        if (\App\Services\AuthSessionService::prefersCookieSession($request)) {
            \App\Services\AuthSessionService::loginUser($request, $user);
            $authPayload = \App\Services\AuthSessionService::authPayload($user);
        } else {
            $token = $user->createToken('auth_token')->plainTextToken;
            $authPayload = \App\Services\AuthSessionService::authPayload($user);
            $authPayload['token'] = $token;
        }

        return $this->success([
            'token' => $authPayload['token'],
            'refresh_token' => null,
            'org_id' => $result['org_id'],
            'branches' => $authPayload['branches'],
            'user' => $authPayload['user'],
            'organization' => $result['organization'],
        ], 'Organization created successfully.', 201);
    }

    public function show($id)
    {
        try {
            $user = request()->user();
            if (! $user || (string) $user->organization_id !== (string) $id) {
                return $this->error('Unauthorized organization context.', null, null, null, 403);
            }

            $organization = Organization::where('id', $id)->firstOrFail();
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
            $user = $request->user();
            if (! $user || (string) $user->organization_id !== (string) $id) {
                return $this->error('Unauthorized organization context.', null, null, null, 403);
            }

            if (! method_exists($user, 'isFullAdmin') || ! $user->isFullAdmin()) {
                return $this->error('Admin access required.', null, null, null, 403);
            }

            $organization = Organization::where('id', $id)->firstOrFail();
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
            $user = request()->user();
            if (! $user || (string) $user->organization_id !== (string) $id) {
                return $this->error('Unauthorized organization context.', null, null, null, 403);
            }

            if (! method_exists($user, 'isFullAdmin') || ! $user->isFullAdmin()) {
                return $this->error('Admin access required.', null, null, null, 403);
            }

            $organization = Organization::where('id', $id)->firstOrFail();
            $organization->delete();

            return $this->success(null, 'Organization successfully deleted.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Organization not found.', null, null, null, 404);
        }
    }

    /**
     * Tenant-scoped search — only the caller's own organization.
     */
    public function search(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->organization_id) {
            return $this->error('Unauthorized organization context.', null, null, null, 403);
        }

        $query = trim((string) $request->query('query', ''));
        $perPage = ApiPagination::perPage($request);

        $builder = Organization::where('id', $user->organization_id);

        if ($query !== '') {
            $like = '%' . addcslashes($query, '%_\\') . '%';
            $builder->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        $results = $builder->paginate($perPage);

        $fieldList = \App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig::getApiFieldsForView('Organization', 'DetailView');

        return $this->paginated(OrganizationResource::collection($results)->resource, $fieldList);
    }
}
