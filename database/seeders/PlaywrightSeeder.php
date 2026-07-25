<?php

namespace Database\Seeders;

use App\Modules\Api\V1\Branch\Models\Branch;
use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\User\Models\User;
use App\Services\DefaultStaffProfilesService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Persistent seed for Playwright E2E.
 *
 * Creates ONE fixed organization + users that the E2E suite logs into every run.
 * Idempotent: safe to run repeatedly. It never re-creates records that already
 * exist, so the Playwright org/user IDs stay stable across runs.
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

        // Admin — full access (dashboard, settings, all modules).
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

        // Branch is a BKModel — its save pipeline resolves organization_id / created_by
        // from the authenticated user, so act as the admin while seeding branches.
        Auth::login($admin);

        // Warehouse (transfers originate here) + a retail branch (transfer destination).
        $warehouse = Branch::firstOrCreate(
            ['organization_id' => $organization->id, 'name' => 'Main'],
            [
                'type' => 'warehouse',
                'address' => '1 Test Street, Automation City',
                'phone' => '+911111111111',
            ]
        );

        $retail = Branch::firstOrCreate(
            ['organization_id' => $organization->id, 'name' => 'E2E Retail Branch'],
            [
                'type' => 'retail',
                'address' => '2 Test Street, Automation City',
                'phone' => '+912222222222',
            ]
        );

        if (! $admin->branch_id) {
            $admin->branch_id = $warehouse->id;
            $admin->save();
        }

        // Warehouse staff — scoped to warehouse modules (ingredients, production, transfers).
        User::firstOrCreate(
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

        // Sales staff — scoped to retail branch (POS / billing / daily report).
        User::firstOrCreate(
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

        // Default staff profiles + roles (same as real org creation).
        app(DefaultStaffProfilesService::class)->ensureForOrganization(
            (string) $organization->id,
            (string) $admin->id
        );

        $this->command?->info('Playwright org ready:');
        $this->command?->line("  organization_id : {$organization->id}");
        $this->command?->line("  warehouse branch: {$warehouse->id}");
        $this->command?->line("  retail branch   : {$retail->id}");
        $this->command?->line('  admin login     : ' . self::ADMIN_EMAIL . ' / ' . self::PASSWORD);
    }
}
