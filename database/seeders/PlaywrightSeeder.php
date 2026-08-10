<?php

namespace Database\Seeders;

use App\Modules\Api\V1\Branch\Models\Branch;
use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\User\Models\User;
use App\Services\DefaultStaffProfilesService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Persistent seed for Playwright E2E.
 *
 *   php artisan db:seed --class=Database\\Seeders\\PlaywrightSeeder
 *
 * Fixed credentials (keep in sync with bk-frontend/e2e/constants.ts):
 *   Admin:          e2e.admin@bk.test    / Playwright@123
 *   Warehouse staff: e2e.warehouse@bk.test / Playwright@123
 *   Sales staff:     e2e.sales@bk.test     / Playwright@123
 */
class PlaywrightSeeder extends Seeder
{
    public const ORG_EMAIL = 'e2e.org@bk.test';
    public const ADMIN_EMAIL = 'e2e.admin@bk.test';
    public const WAREHOUSE_EMAIL = 'e2e.warehouse@bk.test';
    public const SALES_EMAIL = 'e2e.sales@bk.test';
    public const PASSWORD = 'Playwright@123';

    public function run(): void
    {
        $organization = Organization::firstOrCreate(
            ['email' => self::ORG_EMAIL],
            [
                'name' => 'Playwright E2E Bakery',
                'description' => 'Fixed organization used by the Playwright E2E suite. Do not delete.',
                'phone' => '+911111111111',
                'address' => '1 Test Street, Automation City',
            ]
        );

        $admin = User::firstOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'organization_id' => $organization->id,
                'first_name' => 'E2E',
                'last_name' => 'Admin',
                'phone' => '+911111111111',
                'role' => 'admin',
                'is_active' => 1,
                'password' => Hash::make(self::PASSWORD),
            ]
        );

        Auth::login($admin);

        $warehouse = $this->findOrCreateBranch($organization->id, 'Main', [
            'type' => 'warehouse',
            'address' => '1 Test Street, Automation City',
            'phone' => '+911111111111',
        ]);

        $retail = $this->findOrCreateBranch($organization->id, 'E2E Retail Branch', [
            'type' => 'retail',
            'address' => '2 Test Street, Automation City',
            'phone' => '+912222222222',
        ]);

        if (! $admin->branch_id) {
            $admin->branch_id = $warehouse->id;
            $admin->save();
        }

        $warehouseUser = User::firstOrCreate(
            ['email' => self::WAREHOUSE_EMAIL],
            [
                'organization_id' => $organization->id,
                'branch_id' => $warehouse->id,
                'first_name' => 'E2E',
                'last_name' => 'Warehouse',
                'phone' => '+913333333333',
                'role' => 'staff',
                'is_active' => 1,
                'password' => Hash::make(self::PASSWORD),
            ]
        );

        $salesUser = User::firstOrCreate(
            ['email' => self::SALES_EMAIL],
            [
                'organization_id' => $organization->id,
                'branch_id' => $retail->id,
                'first_name' => 'E2E',
                'last_name' => 'Sales',
                'phone' => '+914444444444',
                'role' => 'staff',
                'is_active' => 1,
                'password' => Hash::make(self::PASSWORD),
            ]
        );

        if ((string) $warehouseUser->branch_id !== (string) $warehouse->id) {
            $warehouseUser->branch_id = $warehouse->id;
            $warehouseUser->save();
        }
        if ((string) $salesUser->branch_id !== (string) $retail->id) {
            $salesUser->branch_id = $retail->id;
            $salesUser->save();
        }

        app(DefaultStaffProfilesService::class)->ensureForOrganization(
            (string) $organization->id,
            (string) $admin->id
        );

        $this->assignRoleToUser((string) $organization->id, $warehouseUser->id, 'Warehouse');
        $this->assignRoleToUser((string) $organization->id, $salesUser->id, 'Sales');

        $this->command?->info('Playwright org ready:');
        $this->command?->line("  organization_id : {$organization->id}");
        $this->command?->line("  warehouse branch: {$warehouse->id}");
        $this->command?->line("  retail branch   : {$retail->id}");
        $this->command?->line('  admin login     : ' . self::ADMIN_EMAIL . ' / ' . self::PASSWORD);
    }

    /**
     * BKModel::fill() only maps CRM fields, so firstOrCreate drops `name`.
     * Assign attributes directly instead.
     */
    private function findOrCreateBranch(string $orgId, string $name, array $attrs): Branch
    {
        $existing = Branch::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('name', $name)
            ->first();

        if ($existing) {
            return $existing;
        }

        $branch = new Branch();
        $branch->organization_id = $orgId;
        $branch->name = $name;
        foreach ($attrs as $key => $value) {
            $branch->{$key} = $value;
        }
        $branch->save();

        return $branch;
    }

    private function assignRoleToUser(string $orgId, string $userId, string $roleName): void
    {
        $role = DB::table('roles')
            ->where('organization_id', $orgId)
            ->where('name', $roleName)
            ->where('deleted', 0)
            ->first();

        if (!$role) {
            return;
        }

        $exists = DB::table('role_user_rel')
            ->where('organization_id', $orgId)
            ->where('user_id', $userId)
            ->where('role_id', $role->id)
            ->exists();

        if (!$exists) {
            DB::table('role_user_rel')->insert([
                'role_id' => $role->id,
                'organization_id' => $orgId,
                'user_id' => $userId,
            ]);
        }
    }
}
