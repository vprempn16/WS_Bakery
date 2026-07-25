<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds default staff Profiles + Roles for an organization:
 * - Warehouse Staff (warehouse modules)
 * - Sales Staff (POS / billing modules)
 *
 * Admin / superadmin do not use these profiles.
 */
class DefaultStaffProfilesService
{
    /** @var array<string, array{description: string, modules: array<string, array<string, int>>}> */
    private const PROFILES = [
        'Warehouse Staff' => [
            'description' => 'Default warehouse profile — ingredients, production, transfers, stock.',
            'modules' => [
                'Ingredient' => ['view' => 1, 'create' => 1, 'edit' => 1, 'delete' => 0],
                'Vendor' => ['view' => 1, 'create' => 1, 'edit' => 1, 'delete' => 0],
                'InventoryTransaction' => ['view' => 1, 'create' => 1, 'edit' => 1, 'delete' => 0],
                'Product' => ['view' => 1, 'create' => 1, 'edit' => 1, 'delete' => 0],
                'Recipe' => ['view' => 1, 'create' => 1, 'edit' => 1, 'delete' => 0],
                'ProductionBatch' => ['view' => 1, 'create' => 1, 'edit' => 1, 'delete' => 0],
                'BranchStock' => ['view' => 1, 'create' => 1, 'edit' => 1, 'delete' => 0],
                'BranchTransfer' => ['view' => 1, 'create' => 1, 'edit' => 1, 'delete' => 0],
                'Branch' => ['view' => 1, 'create' => 0, 'edit' => 0, 'delete' => 0],
            ],
        ],
        'Sales Staff' => [
            'description' => 'Default sales / POS profile — billing, product view, branch stock, daily report.',
            'modules' => [
                'Billing' => ['view' => 1, 'create' => 1, 'edit' => 1, 'delete' => 0],
                'Product' => ['view' => 1, 'create' => 0, 'edit' => 0, 'delete' => 0],
                'BranchStock' => ['view' => 1, 'create' => 0, 'edit' => 0, 'delete' => 0],
                'BranchDailyReport' => ['view' => 1, 'create' => 1, 'edit' => 1, 'delete' => 0],
            ],
        ],
    ];

    /** Role display name => profile name */
    private const ROLES = [
        'Warehouse' => 'Warehouse Staff',
        'Sales' => 'Sales Staff',
    ];

    /**
     * Ensure default profiles + roles exist for one organization.
     *
     * @return array{profiles: int, roles: int}
     */
    public function ensureForOrganization(string $organizationId, ?string $createdByUserId = null): array
    {
        if (! Schema::hasTable('profiles') || ! Schema::hasTable('profile_module_actions')) {
            return ['profiles' => 0, 'roles' => 0];
        }

        $this->ensureSystemActions();

        $actionMap = DB::table('system_actions')->pluck('id', 'action_key')->toArray();
        $createdBy = $createdByUserId
            ?: DB::table('users')->where('organization_id', $organizationId)->whereIn('role', ['admin', 'superadmin'])->value('id')
            ?: DB::table('users')->where('organization_id', $organizationId)->value('id')
            ?: 'setup';

        $profileIds = [];
        $profilesTouched = 0;

        foreach (self::PROFILES as $name => $def) {
            $profileId = $this->upsertProfile($organizationId, $name, $def['description']);
            $this->syncModuleActions($profileId, $organizationId, $def['modules'], $actionMap);
            app(ProfileDataGeneratorService::class)->generate($profileId, $organizationId);
            $profileIds[$name] = $profileId;
            $profilesTouched++;
        }

        $rolesTouched = 0;
        if (Schema::hasTable('roles') && Schema::hasTable('role_profile_rel')) {
            foreach (self::ROLES as $roleName => $profileName) {
                if (! isset($profileIds[$profileName])) {
                    continue;
                }
                $this->upsertRole($organizationId, $roleName, $profileIds[$profileName], (string) $createdBy);
                $rolesTouched++;
            }
        }

        return ['profiles' => $profilesTouched, 'roles' => $rolesTouched];
    }

    /**
     * Seed for every organization currently in the DB.
     */
    public function ensureForAllOrganizations(): array
    {
        if (! Schema::hasTable('organizations')) {
            return ['orgs' => 0, 'profiles' => 0, 'roles' => 0];
        }

        $orgIds = DB::table('organizations')->pluck('id');
        $totals = ['orgs' => 0, 'profiles' => 0, 'roles' => 0];

        foreach ($orgIds as $orgId) {
            $result = $this->ensureForOrganization((string) $orgId);
            $totals['orgs']++;
            $totals['profiles'] += $result['profiles'];
            $totals['roles'] += $result['roles'];
        }

        return $totals;
    }

    private function ensureSystemActions(): void
    {
        if (! Schema::hasTable('system_actions')) {
            return;
        }

        $now = now();
        foreach (
            [
                ['id' => 1, 'action_key' => 'view', 'label' => 'View'],
                ['id' => 2, 'action_key' => 'create', 'label' => 'Create'],
                ['id' => 3, 'action_key' => 'edit', 'label' => 'Edit'],
                ['id' => 4, 'action_key' => 'delete', 'label' => 'Delete'],
            ] as $action
        ) {
            if (DB::table('system_actions')->where('id', $action['id'])->exists()) {
                continue;
            }
            DB::table('system_actions')->insert([
                'id' => $action['id'],
                'action_key' => $action['action_key'],
                'label' => $action['label'],
                'security_check' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function upsertProfile(string $orgId, string $name, string $description): int
    {
        $existing = DB::table('profiles')
            ->where('organization_id', $orgId)
            ->where('name', $name)
            ->where('deleted', 0)
            ->first();

        if ($existing) {
            DB::table('profiles')->where('id', $existing->id)->update([
                'description' => $description,
                'status' => 'Active',
                'updated_at' => now(),
            ]);

            return (int) $existing->id;
        }

        $nextId = ((int) DB::table('profiles')->max('id')) + 1;
        DB::table('profiles')->insert([
            'id' => $nextId,
            'organization_id' => $orgId,
            'name' => $name,
            'description' => $description,
            'status' => 'Active',
            'deleted' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $nextId;
    }

    /**
     * @param  array<string, array<string, int>>  $modules
     * @param  array<string, int|string>  $actionMap
     */
    private function syncModuleActions(int $profileId, string $orgId, array $modules, array $actionMap): void
    {
        DB::table('profile_module_actions')->where('profileid', $profileId)->delete();

        $now = now();
        $rows = [];
        foreach ($modules as $moduleName => $actions) {
            foreach ($actions as $actionKey => $permission) {
                if (! isset($actionMap[$actionKey])) {
                    continue;
                }
                $rows[] = [
                    'profileid' => $profileId,
                    'organization_id' => $orgId,
                    'modulename' => $moduleName,
                    'action_id' => $actionMap[$actionKey],
                    'permission' => (int) $permission,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            DB::table('profile_module_actions')->insert($rows);
        }
    }

    private function upsertRole(string $orgId, string $roleName, int $profileId, string $createdBy): void
    {
        $role = DB::table('roles')
            ->where('organization_id', $orgId)
            ->where('name', $roleName)
            ->where('deleted', 0)
            ->first();

        if ($role) {
            $roleId = (int) $role->id;
            DB::table('roles')->where('id', $roleId)->update([
                'status' => 1,
                'description' => "Default {$roleName} role (linked to staff profile)",
                'updated_at' => now(),
            ]);
        } else {
            $roleId = (int) DB::table('roles')->insertGetId([
                'name' => $roleName,
                'description' => "Default {$roleName} role (linked to staff profile)",
                'status' => 1,
                'organization_id' => $orgId,
                'created_by' => $createdBy,
                'deleted' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('role_profile_rel')->where('role_id', $roleId)->where('organization_id', $orgId)->delete();
        DB::table('role_profile_rel')->insert([
            'role_id' => $roleId,
            'organization_id' => $orgId,
            'profile_id' => $profileId,
        ]);
    }
}
