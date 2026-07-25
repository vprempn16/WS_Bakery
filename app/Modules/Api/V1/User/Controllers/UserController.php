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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * GET settings/User/new — create-view fields.
     */
    public function createForm()
    {
        $fields = ModuleFieldConfig::getApiFieldsForView('User', 'CreateView');
        $fields = array_map(function ($field) {
            if (($field['fieldname'] ?? '') === 'role') {
                $field['options'] = [];
            }
            return $field;
        }, $fields);

        return $this->success([
            'fields' => $fields,
            'values' => [
                'status' => 1,
                'is_active' => 1,
            ],
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

        if ($request->has('savedFilterId')) {
            $savedFilter = \App\Modules\Api\V1\SavedFilter\Models\SavedFilter::where('organization_id', $orgId)
                ->findOrFail($request->query('savedFilterId'));
            \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'users', $savedFilter->rules);
        }

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

        $fieldList = array_map(function ($field) {
            if (($field['fieldname'] ?? '') === 'role') {
                $field['options'] = [];
            }
            return $field;
        }, ModuleFieldConfig::getApiFieldsForView('User', 'DetailView'));

        return $this->paginated(UserResource::collection($users)->resource, $fieldList);
    }

    public function store(StoreUserRequest $request)
    {
        $values = $this->normalizeUserValues($request->input('data.values', []));
        $orgId = AuthUser::organizationId();

        // Never create admin/superadmin via User API — org register / setup only.
        if ($this->isFullAdminRoleString($values['role'] ?? '')) {
            return $this->error('Cannot create an admin user from User settings.', null, null, null, 422);
        }

        if (empty($values['roleId'])) {
            return $this->error('A Settings Role is required for staff users.', null, null, null, 422);
        }

        if (! $this->validateSettingsRole($values['roleId'], $orgId)) {
            return $this->error('Invalid role for this organization.', null, null, null, 422);
        }

        $user = User::create([
            'organization_id' => $orgId,
            'branch_id' => $values['branchId'] ?? null,
            'first_name' => $values['firstName'],
            'last_name' => $values['lastName'],
            'email' => $values['email'],
            'phone' => $values['phone'] ?? null,
            'role' => 'staff',
            'is_active' => $values['is_active'] ?? 1,
            'password' => Hash::make($values['password']),
        ]);

        $this->syncRoleAssignment($user, $values['roleId'], $orgId);

        return $this->success(new UserResource($user), 'User created successfully.', 201);
    }

    public function show($id)
    {
        try {
            $orgId = AuthUser::organizationId();
            $user = User::with(['organization', 'branch'])
                ->where('organization_id', $orgId)
                ->findOrFail($id);
            $resource = new UserResource($user);
            $fieldList = ModuleFieldConfig::getApiFieldsForView('User', 'DetailView');

            // Admin / superadmin: role / branch are view-only.
            if ($user->isFullAdmin()) {
                $fieldList = array_map(function ($field) {
                    if (in_array($field['fieldname'] ?? '', ['role', 'branchId'], true)) {
                        $field['displaytype'] = 3;
                        $field['disabled'] = true;
                    }
                    if (($field['fieldname'] ?? '') === 'role') {
                        $field['options'] = [];
                    }
                    return $field;
                }, $fieldList);
            } else {
                $fieldList = array_map(function ($field) {
                    if (($field['fieldname'] ?? '') === 'role') {
                        $field['options'] = [];
                    }
                    return $field;
                }, $fieldList);
            }

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

            // Admin / superadmin: lock role + is_active + branch.
            if ($user->isFullAdmin()) {
                $keptRole = strtolower((string) $user->role) === 'superadmin' ? 'superadmin' : 'admin';
                $data = [
                    'first_name' => $values['firstName'],
                    'last_name' => $values['lastName'],
                    'email' => $values['email'],
                    'phone' => $values['phone'] ?? null,
                    'role' => $keptRole,
                    'is_active' => 1,
                    'branch_id' => $user->branch_id,
                ];

                if (! empty($values['password'])) {
                    $data['password'] = Hash::make($values['password']);
                }

                $user->update($data);
                // Full admins never use Settings Role assignment.
                DB::table('role_user_rel')
                    ->where('user_id', $user->id)
                    ->where('organization_id', $orgId)
                    ->delete();

                return $this->success(new UserResource($user->fresh(['organization', 'branch'])), 'User updated successfully.');
            }

            if (empty($values['roleId'])) {
                return $this->error('A Settings Role is required for staff users.', null, null, null, 422);
            }

            if (! $this->validateSettingsRole($values['roleId'], $orgId)) {
                return $this->error('Invalid role for this organization.', null, null, null, 422);
            }

            $data = [
                'first_name' => $values['firstName'],
                'last_name' => $values['lastName'],
                'email' => $values['email'],
                'phone' => $values['phone'] ?? null,
                'role' => 'staff',
                'is_active' => $values['is_active'] ?? (int) ($user->is_active ?? 1),
                'branch_id' => array_key_exists('branchId', $values) ? ($values['branchId'] ?: null) : $user->branch_id,
            ];

            if (! empty($values['password'])) {
                $data['password'] = Hash::make($values['password']);
            }

            $user->update($data);
            $this->syncRoleAssignment($user, $values['roleId'], $orgId);

            return $this->success(new UserResource($user->fresh(['organization', 'branch'])), 'User updated successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('User not found.', null, null, null, 404);
        }
    }

    public function destroy($id)
    {
        try {
            $orgId = AuthUser::organizationId();
            $user = User::where('organization_id', $orgId)->findOrFail($id);

            if ($user->isFullAdmin()) {
                return $this->error('Cannot delete an admin or superadmin user.', null, null, null, 422);
            }

            DB::table('role_user_rel')
                ->where('user_id', $user->id)
                ->where('organization_id', $orgId)
                ->delete();

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
        $roleId = null;
        if (isset($values['roleId'])) {
            $roleId = is_array($values['roleId']) ? ($values['roleId'][0] ?? null) : $values['roleId'];
        }
        // FE picklist may send selected Settings Role id in `role`.
        if ($roleId === null && isset($values['role']) && ! $this->isFullAdminRoleString((string) $values['role'])) {
            if (is_numeric($values['role']) || (is_string($values['role']) && ctype_digit((string) $values['role']))) {
                $roleId = $values['role'];
            }
        }

        $isActive = null;
        if (array_key_exists('is_active', $values)) {
            $isActive = ((int) $values['is_active'] === 1 || $values['is_active'] === true || $values['is_active'] === '1') ? 1 : 0;
        } elseif (array_key_exists('status', $values)) {
            $status = $values['status'];
            if (is_bool($status) || is_numeric($status) || $status === '1' || $status === '0') {
                $isActive = ((int) $status === 1) ? 1 : 0;
            } else {
                $isActive = strcasecmp((string) $status, 'Active') === 0 ? 1 : 0;
            }
        }

        return [
            'firstName' => $values['firstName'] ?? '',
            'lastName' => $values['lastName'] ?? '',
            'email' => $values['email'] ?? '',
            'phone' => $values['phone'] ?? $values['phoneNumber'] ?? null,
            'role' => isset($values['role']) ? (string) $values['role'] : '',
            'roleId' => $roleId !== null && $roleId !== '' ? (int) $roleId : null,
            'password' => $values['password'] ?? null,
            'confirmPassword' => $values['confirmPassword'] ?? null,
            'branchId' => $values['branchId'] ?? $values['branch_id'] ?? null,
            'is_active' => $isActive,
        ];
    }

    private function isFullAdminRoleString(string $role): bool
    {
        return in_array(strtolower($role), ['admin', 'superadmin', 'owner'], true);
    }

    private function validateSettingsRole(?int $roleId, string $orgId): bool
    {
        if (! $roleId) {
            return false;
        }

        return DB::table('roles')
            ->where('id', $roleId)
            ->where('organization_id', $orgId)
            ->where('deleted', 0)
            ->where(function ($q) {
                $q->where('status', 1)->orWhere('status', '1')->orWhere('status', 'Active');
            })
            ->exists();
    }

    private function syncRoleAssignment(User $user, ?int $roleId, string $orgId): void
    {
        DB::table('role_user_rel')
            ->where('user_id', $user->id)
            ->where('organization_id', $orgId)
            ->delete();

        if (! $roleId) {
            return;
        }

        DB::table('role_user_rel')->insert([
            'role_id' => $roleId,
            'organization_id' => $orgId,
            'user_id' => $user->id,
        ]);
    }
}
