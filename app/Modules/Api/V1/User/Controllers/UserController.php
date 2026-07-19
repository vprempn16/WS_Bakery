<?php

namespace App\Modules\Api\V1\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig;
use App\Modules\Api\V1\User\Models\User;
use App\Modules\Api\V1\User\Requests\StoreUserRequest;
use App\Modules\Api\V1\User\Requests\UpdateUserRequest;
use App\Modules\Api\V1\User\Resources\UserResource;
use App\Services\AuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * GET settings/User/new — create-view fields.
     */
    public function createForm()
    {
        return $this->success([
            'fields' => ModuleFieldConfig::getApiFieldsForView('User', 'CreateView'),
            'values' => [],
        ]);
    }

    public function index(Request $request)
    {
        $orgId = AuthUser::organizationId();
        $perPage = $request->query('per_page', 20);

        $query = User::where('organization_id', $orgId);

        $query->when($request->query('role'), function ($q, $role) {
            $q->where('role', $role);
        });

        $query->when($request->query('search'), function ($q, $search) {
            $q->where(function ($inner) use ($search) {
                $inner->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
            });
        });

        // Apply saved filter if provided
        if ($request->has('savedFilterId')) {
            $savedFilter = \App\Modules\Api\V1\SavedFilter\Models\SavedFilter::where('organization_id', $orgId)
                ->findOrFail($request->query('savedFilterId'));
            \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'users', $savedFilter->rules);
        }

        // Apply dynamic query rules if provided
        if ($request->has('rules')) {
            $rules = $request->input('rules');
            if (is_string($rules)) {
                $rules = json_decode($rules, true);
            }
            if (is_array($rules)) {
                \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'users', $rules);
            }
        }

        $users = $query->paginate($perPage);

        $fieldList = ModuleFieldConfig::getApiFieldsForView('User', 'DetailView');

        return $this->paginated(UserResource::collection($users)->resource, $fieldList);
    }

    public function store(StoreUserRequest $request)
    {
        $values = $this->normalizeUserValues($request->input('data.values', []));
        $orgId = AuthUser::organizationId();

        $user = User::create([
            'organization_id' => $orgId,
            'branch_id' => $values['branchId'] ?? null,
            'first_name' => $values['firstName'],
            'last_name' => $values['lastName'],
            'email' => $values['email'],
            'phone' => $values['phone'] ?? null,
            'role' => $values['role'],
            'password' => Hash::make($values['password']),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success(new UserResource($user, $token), 'User created successfully.', 201);
    }

    public function show($id)
    {
        try {
            $orgId = AuthUser::organizationId();
            $user = User::where('organization_id', $orgId)->findOrFail($id);
            $resource = new UserResource($user);
            $fieldList = ModuleFieldConfig::getApiFieldsForView('User', 'DetailView');

            return $this->success([
                'fields' => $fieldList,
                'values' => $resource->toArray(request()),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('User not found.', null, null, null, 404);
        }
    }

    public function update(UpdateUserRequest $request, $id)
    {
        try {
            $orgId = AuthUser::organizationId();
            $user = User::where('organization_id', $orgId)->findOrFail($id);
            $values = $this->normalizeUserValues($request->input('data.values', []));

            $data = [
                'first_name' => $values['firstName'],
                'last_name' => $values['lastName'],
                'email' => $values['email'],
                'phone' => $values['phone'] ?? null,
                'role' => $values['role'],
                'branch_id' => array_key_exists('branchId', $values) ? ($values['branchId'] ?: null) : $user->branch_id,
            ];

            if (!empty($values['password'])) {
                $data['password'] = Hash::make($values['password']);
            }

            $user->update($data);

            return $this->success(new UserResource($user), 'User updated successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('User not found.', null, null, null, 404);
        }
    }

    public function destroy($id)
    {
        try {
            $orgId = AuthUser::organizationId();
            $user = User::where('organization_id', $orgId)->findOrFail($id);
            $user->delete();

            return $this->success(null, 'User successfully deleted.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('User not found.', null, null, null, 404);
        }
    }

    /**
     * Accept both bakery field names and settings UI aliases.
     */
    private function normalizeUserValues(array $values): array
    {
        $role = $values['role'] ?? null;
        if ($role === null && isset($values['roleId'])) {
            $role = is_array($values['roleId']) ? ($values['roleId'][0] ?? null) : $values['roleId'];
        }

        return [
            'firstName' => $values['firstName'] ?? '',
            'lastName' => $values['lastName'] ?? '',
            'email' => $values['email'] ?? '',
            'phone' => $values['phone'] ?? $values['phoneNumber'] ?? null,
            'role' => $role !== null ? (string) $role : '',
            'password' => $values['password'] ?? null,
            'confirmPassword' => $values['confirmPassword'] ?? null,
            'branchId' => $values['branchId'] ?? $values['branch_id'] ?? null,
        ];
    }
}
