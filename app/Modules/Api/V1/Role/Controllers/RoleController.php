<?php

namespace App\Modules\Api\V1\Role\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\Profile\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    /**
     * GET settings/Role/new — create form fields.
     */
    public function createForm()
    {
        return $this->success([
            'fields' => [
                ['fieldname' => 'name', 'fieldtype' => 'text', 'mandatory' => true, 'fieldlabel' => 'Role Name'],
                ['fieldname' => 'description', 'fieldtype' => 'textarea', 'mandatory' => false, 'fieldlabel' => 'Description'],
                [
                    'fieldname' => 'status',
                    'fieldlabel' => 'Status',
                    'fieldtype' => 'checkbox',
                    'mandatory' => false,
                    'displaytype' => 1,
                ],
                [
                    'fieldname' => 'profile_ids',
                    'fieldtype' => 'multirelationpicklist',
                    'mandatory' => true,
                    'fieldlabel' => 'Profiles',
                    'options' => [],
                ],
            ],
            'values' => [
                'name' => '',
                'description' => '',
                'status' => 1,
                'profile_ids' => [],
            ],
        ]);
    }

    public function index(Request $request)
{
    try {

        $user  = auth()->user();
        $orgId = $user->organization_id;

        $perPage = (int) $request->query('per_page', 20);
        $page    = max((int) $request->query('page', 1), 1);
        $offset  = ($page - 1) * $perPage;

        $search  = trim($request->query('search', ''));
        $status  = $request->query('status');

        /* ----------------------------------------
         * Base Query
         * --------------------------------------*/
        $query = DB::table('roles')
            ->where('organization_id', $orgId)
            ->where('deleted', 0);

        if ($search !== '') {
            // Escape special LIKE characters to prevent SQL injection
            $escapedSearch = addcslashes($search, '%_\\');
            $query->where(function ($q) use ($escapedSearch) {
                $q->where('name', 'like', "%{$escapedSearch}%")
                  ->orWhere('description', 'like', "%{$escapedSearch}%");
            });
        }

        if (!empty($status)) {
            $query->where('status', $status);
        }

        /* ----------------------------------------
         * Count
         * --------------------------------------*/
        $total = $query->count();

        /* ----------------------------------------
         * Data
         * --------------------------------------*/
        $roles = $query
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        /* ----------------------------------------
         * Profiles Mapping
         * --------------------------------------*/
        $roleIds = $roles->pluck('id')->toArray();

        $profileMap = DB::table('role_profile_rel')
            ->whereIn('role_id', $roleIds)
            ->get()
            ->groupBy('role_id')
            ->map(fn ($rows) => $rows->pluck('profile_id')->values());

        $list = $roles->map(function ($role) use ($profileMap) {
            return [
                'id'          => $role->id,
                'name'        => $role->name,
                'description' => $role->description,
                'status'      => $this->roleStatusToCheckbox($role->status),
                'profile_ids' => $profileMap[$role->id] ?? [],
                'created_at'  => $role->created_at,
                'updated_at'  => $role->updated_at,
            ];
        });
        return $this->success([
            'list'   => $list,
            'meta'   => [
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $perPage,
                'last_page' => ceil($total / $perPage),
            ],
            'links'  => [], // optional (frontend pagination)
        ]);

    } catch (\Exception $e) {
        \Log::error('Role index failed', [
            'error' => $e->getMessage(),
        ]);

        return $this->errorFromException($e, 'Failed to fetch roles');
    }
}

    /**
     * POST settings/Role/new
     */
    public function store(Request $request)
    {
        return $this->saveRole($request, null);
    }

    /**
     * POST settings/Role/{id}
     */
    public function update(Request $request, $id)
    {
        return $this->saveRole($request, $id);
    }

    private function saveRole(Request $request, $roleId = null)
    {
        try {
            $data = $request->input('data', []);

            if (empty($data)) {
                return $this->error('Invalid payload');
            }

            $user = auth()->user();
            $orgId = $user->organization_id;

            // Prefer route id; fall back to body id for older clients
            if ($roleId === null || $roleId === 'new') {
                $roleId = $data['id'] ?? null;
            }
            if ($roleId === 'new' || $roleId === '' || $roleId === null) {
                $roleId = null;
            }

            if (empty($data['name']) || ! array_key_exists('status', $data)) {
                return $this->error('Role name and status are required');
            }

            $statusValue = $this->normalizeRoleStatus($data['status']);

            if (isset($data['profile_ids']) && !empty($data['profile_ids'])) {
                $profileIds = array_unique(array_map('intval', (array) $data['profile_ids']));
                $validCount = Profile::whereIn('id', $profileIds)
                    ->where('organization_id', $orgId)
                    ->where('deleted', 0)
                    ->count();
                if ($validCount !== count($profileIds)) {
                    return $this->error('One or more profile IDs are invalid or do not belong to your organization');
                }
            }

            DB::beginTransaction();

            if ($roleId !== null) {
                $role = DB::table('roles')
                    ->where('id', $roleId)
                    ->where('organization_id', $orgId)
                    ->where('deleted', 0)
                    ->first();

                if (!$role) {
                    DB::rollBack();
                    return $this->error("Role not found with ID {$roleId}");
                }

                DB::table('roles')
                    ->where('id', $roleId)
                    ->update([
                        'name' => $data['name'],
                        'description' => $data['description'] ?? null,
                        'status' => $statusValue,
                        'updated_at' => now(),
                    ]);

                if (isset($data['profile_ids'])) {
                    DB::table('role_profile_rel')->where('role_id', $roleId)->delete();
                    foreach ((array) $data['profile_ids'] as $profileId) {
                        DB::table('role_profile_rel')->insert([
                            'role_id' => $roleId,
                            'organization_id' => $orgId,
                            'profile_id' => $profileId,
                        ]);
                    }
                }

                DB::commit();

                return $this->success(['id' => $roleId], 'Role updated successfully');
            }

            $newRoleId = DB::table('roles')->insertGetId([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => $statusValue,
                'organization_id' => $orgId,
                'created_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (!empty($data['profile_ids'])) {
                foreach ((array) $data['profile_ids'] as $profileId) {
                    DB::table('role_profile_rel')->insert([
                        'role_id' => $newRoleId,
                        'organization_id' => $orgId,
                        'profile_id' => $profileId,
                    ]);
                }
            }

            DB::commit();

            return $this->success(['id' => $newRoleId], 'Role created successfully');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Role save failed', [
                'error' => $e->getMessage(),
                'data' => $request->input('data'),
            ]);

            return $this->error('Failed to save role: ' . $e->getMessage());
        }
    }

    /* =====================================================
     * SHOW
     * ===================================================== */
    public function show(Request $request, int $id)
    {

        try {
            $orgId = auth()->user()->organization_id;

            $role = DB::table('roles')
                ->where('id', $id)
                ->where('organization_id', $orgId)
                ->where('deleted', 0)
                ->first();

            if (!$role) {
                return $this->error("Role not found with ID {$id}");
            }

            $profiles = DB::table('role_profile_rel')
                ->where('role_id', $id)
                ->pluck('profile_id')
                ->values();

            return $this->success([
                'id'          => $role->id,
                'name'        => $role->name,
                'description' => $role->description,
                'status'      => $this->roleStatusToCheckbox($role->status),
                'profile_ids' => $profiles,
                'created_at'  => $role->created_at,
                'updated_at'  => $role->updated_at,
            ]);

        } catch (\Throwable $e) {
            Log::error('Role show failed', [
                'role_id' => $id,
                'error'   => $e->getMessage(),
            ]);

            return $this->error('Failed to fetch role');
        }
    }

    /**
     * Checkbox / bool / Active|Inactive / 0|1 → DB value 1 or 0.
     */
    private function normalizeRoleStatus(mixed $status): int
    {
        if (is_bool($status) || is_numeric($status) || $status === '1' || $status === '0') {
            return ((int) $status === 1) ? 1 : 0;
        }

        return strcasecmp((string) $status, 'Active') === 0 ? 1 : 0;
    }

    /** DB / legacy Active|Inactive → checkbox 1|0 */
    private function roleStatusToCheckbox(mixed $status): int
    {
        if (is_bool($status) || is_numeric($status) || $status === '1' || $status === '0') {
            return ((int) $status === 1) ? 1 : 0;
        }

        return strcasecmp((string) $status, 'Active') === 0 ? 1 : 0;
    }

    /* =====================================================
     * DELETE (SOFT)
     * ===================================================== */
    public function delete(Request $request, int $id)
    {

        try {
            $user  = auth()->user();
            $orgId = $user->organization_id;

            $role = DB::table('roles')
                ->where('id', $id)
                ->where('organization_id', $orgId)
                ->where('deleted', 0)
                ->first();

            if (!$role) {
                return $this->error("Role not found with ID {$id}");
            }

            DB::beginTransaction();

            DB::table('roles')
                ->where('id', $id)
                ->update([
                    'deleted'     => 1
                ]);

            DB::table('role_profile_rel')
                ->where('role_id', $id)
                ->delete();

            DB::commit();

            Log::info('Role deleted', [
                'role_id' => $id,
                'user_id' => $user->id,
            ]);

            return $this->success(
                ['id' => $id],
                'Role deleted successfully'
            );

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Role delete failed', [
                'role_id' => $id,
                'error'   => $e->getMessage(),
            ]);

            return $this->error('Failed to delete role');
        }
    }
}