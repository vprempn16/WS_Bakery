<?php

namespace Database\Seeders;

use App\Modules\Api\V1\Branch\Models\Branch;
use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\User\Models\User;
use App\Modules\Api\V1\Vendor\Models\Vendor;
use App\Services\DefaultCatalogService;
use App\Services\DefaultStaffProfilesService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Isolated Client Demo Bakery (Chennai) — catalog from client-pvt.
 * Wipes this org's old ingredients/products and all batches/POS/transfers, then reseeds.
 *
 * php artisan db:seed --class=Database\\Seeders\\ClientDemoBakerySeeder
 *
 * Login: demo.admin@client-bakery.test / Demo@12345
 */
class ClientDemoBakerySeeder extends Seeder
{
    private const ORG_EMAIL = 'demo@client-bakery.test';

    private const ADMIN_EMAIL = 'demo.admin@client-bakery.test';

    private const ADMIN_PASSWORD = 'Demo@12345';

    public function run(): void
    {
        $this->command?->info('Seeding Client Demo Bakery (isolated Chennai org)...');

        DB::transaction(function () {
            $org = Organization::firstOrCreate(
                ['email' => self::ORG_EMAIL],
                [
                    'name' => 'Client Demo Bakery',
                    'description' => 'DEMO-BAKERY isolated org for client demos',
                    'phone' => '+914422000001',
                    'address' => '100 Anna Salai, Chennai, Tamil Nadu 600002',
                ]
            );

            $admin = User::updateOrCreate(
                ['email' => self::ADMIN_EMAIL],
                [
                    'organization_id' => $org->id,
                    'first_name' => 'Demo',
                    'last_name' => 'Admin',
                    'password' => Hash::make(self::ADMIN_PASSWORD),
                    'role' => 'admin',
                    'phone' => '+919900000001',
                    'is_active' => 1,
                ]
            );

            Auth::login($admin);

            $warehouse = $this->upsertBranch($org->id, 'BK Central Warehouse', 'warehouse', 'SIPCOT Industrial Estate, Irungattukottai, Chennai', '+914422000010');
            $chennaiMain = $this->upsertBranch($org->id, 'Chennai Main Branch', 'retail', 'T Nagar, Chennai', '+914422000011');
            $annaNagar = $this->upsertBranch($org->id, 'Anna Nagar Branch', 'retail', '2nd Avenue, Anna Nagar, Chennai', '+914422000012');
            $tambaram = $this->upsertBranch($org->id, 'Tambaram Branch', 'retail', 'GST Road, Tambaram, Chennai', '+914422000013');
            $velachery = $this->upsertBranch($org->id, 'Velachery Branch', 'retail', 'Velachery Main Road, Chennai', '+914422000014');

            $admin->branch_id = $warehouse->id;
            $admin->save();

            app(DefaultStaffProfilesService::class)->ensureForOrganization((string) $org->id, (string) $admin->id);

            $warehouseUser = User::updateOrCreate(
                ['email' => 'demo.warehouse@client-bakery.test'],
                [
                    'organization_id' => $org->id,
                    'branch_id' => $warehouse->id,
                    'first_name' => 'Warehouse',
                    'last_name' => 'Staff',
                    'password' => Hash::make(self::ADMIN_PASSWORD),
                    'role' => 'warehouse',
                    'phone' => '+919900000002',
                    'is_active' => 1,
                ]
            );
            $salesUser = User::updateOrCreate(
                ['email' => 'demo.sales@client-bakery.test'],
                [
                    'organization_id' => $org->id,
                    'branch_id' => $annaNagar->id,
                    'first_name' => 'Anna',
                    'last_name' => 'Cashier',
                    'password' => Hash::make(self::ADMIN_PASSWORD),
                    'role' => 'staff',
                    'phone' => '+919900000003',
                    'is_active' => 1,
                ]
            );
            $this->assignRole($org->id, $warehouseUser->id, 'Warehouse');
            $this->assignRole($org->id, $salesUser->id, 'Sales');

            $this->resetOrgCatalog((string) $org->id);

            $vendors = $this->seedVendors($org->id);
            $vendorId = $vendors['Sri Lakshmi Foods']->id ?? null;
            app(DefaultCatalogService::class)->seedForOrganization((string) $org->id, true, $vendorId);

            $this->command?->info('Client Demo Bakery ready (catalog only — no batches or POS).');
            $this->command?->info('  Admin: '.self::ADMIN_EMAIL.' / '.self::ADMIN_PASSWORD);
            $this->command?->info('  Branches: '.$warehouse->name.', '.$chennaiMain->name.', '.$annaNagar->name.', '.$tambaram->name.', '.$velachery->name);
        });

        Auth::logout();
    }

    private function upsertBranch(string $orgId, string $name, string $type, string $address, string $phone): Branch
    {
        return Branch::withoutGlobalScopes()->updateOrCreate(
            ['organization_id' => $orgId, 'name' => $name],
            ['type' => $type, 'address' => $address, 'phone' => $phone]
        );
    }

    private function assignRole(string $orgId, string $userId, string $roleName): void
    {
        $role = DB::table('roles')
            ->where('organization_id', $orgId)
            ->where('name', $roleName)
            ->where('deleted', 0)
            ->first();
        if (! $role) {
            return;
        }
        $exists = DB::table('role_user_rel')
            ->where('organization_id', $orgId)
            ->where('user_id', $userId)
            ->where('role_id', $role->id)
            ->exists();
        if (! $exists) {
            DB::table('role_user_rel')->insert([
                'role_id' => $role->id,
                'organization_id' => $orgId,
                'user_id' => $userId,
            ]);
        }
    }

    /** @return array<string, Vendor> */
    private function seedVendors(string $orgId): array
    {
        $defs = [
            'Sri Lakshmi Foods' => [
                'contact_person' => 'Lakshmi Narayanan',
                'phone' => '+919841000101',
                'email' => 'orders@srilakshmifoods.demo',
                'address' => 'Koyambedu Market, Chennai',
            ],
            'Fresh Dairy Suppliers' => [
                'contact_person' => 'Ravi Kumar',
                'phone' => '+919841000102',
                'email' => 'supply@freshdairy.demo',
                'address' => 'Aavin Road, Madhavaram, Chennai',
            ],
            'Baking Essentials India' => [
                'contact_person' => 'Priya Menon',
                'phone' => '+919841000103',
                'email' => 'sales@bakingessentials.demo',
                'address' => 'Guindy Industrial Estate, Chennai',
            ],
            'Dry Fruits & Nuts Hub' => [
                'contact_person' => 'Imran Khan',
                'phone' => '+919841000104',
                'email' => 'orders@dryfruitshub.demo',
                'address' => 'Sowcarpet, Chennai',
            ],
        ];

        $out = [];
        foreach ($defs as $name => $data) {
            $out[$name] = Vendor::withoutGlobalScopes()->updateOrCreate(
                ['organization_id' => $orgId, 'name' => $name],
                $data
            );
        }

        return $out;
    }

    /**
     * Remove this org's catalog and operational data so the warehouse list can be loaded clean.
     */
    private function resetOrgCatalog(string $orgId): void
    {
        $productIds = Schema::hasTable('products')
            ? DB::table('products')->where('organization_id', $orgId)->pluck('id')
            : collect();
        $billIds = Schema::hasTable('billings')
            ? DB::table('billings')->where('organization_id', $orgId)->pluck('id')
            : collect();
        $transferIds = Schema::hasTable('branch_transfers')
            ? DB::table('branch_transfers')->where('organization_id', $orgId)->pluck('id')
            : collect();
        $planIds = Schema::hasTable('production_plans')
            ? DB::table('production_plans')->where('organization_id', $orgId)->pluck('id')
            : collect();
        $issueIds = Schema::hasTable('material_issues')
            ? DB::table('material_issues')->where('organization_id', $orgId)->pluck('id')
            : collect();
        $returnIds = Schema::hasTable('sales_returns')
            ? DB::table('sales_returns')->where('organization_id', $orgId)->pluck('id')
            : collect();
        $reportIds = Schema::hasTable('branch_daily_reports')
            ? DB::table('branch_daily_reports')->where('organization_id', $orgId)->pluck('id')
            : collect();

        $this->deleteWhereIn('billing_items', 'billing_id', $billIds);
        $this->deleteByOrg('billings', $orgId);

        $this->deleteWhereIn('sales_return_items', 'sales_return_id', $returnIds);
        $this->deleteByOrg('sales_returns', $orgId);

        $this->deleteByOrg('production_batches', $orgId);

        $this->deleteWhereIn('branch_transfer_items', 'branch_transfer_id', $transferIds);
        $this->deleteByOrg('branch_transfers', $orgId);

        $this->deleteByOrg('branch_stocks', $orgId);
        $this->deleteByOrg('product_stock_transactions', $orgId);

        $this->deleteWhereIn('branch_daily_report_items', 'branch_daily_report_id', $reportIds);
        $this->deleteByOrg('branch_daily_reports', $orgId);

        $this->deleteWhereIn('production_plan_items', 'production_plan_id', $planIds);
        $this->deleteByOrg('production_plans', $orgId);

        $this->deleteWhereIn('material_issue_items', 'material_issue_id', $issueIds);
        $this->deleteByOrg('material_issues', $orgId);

        $this->deleteWhereIn('recipes', 'product_id', $productIds);
        $this->deleteByOrg('inventory_transactions', $orgId);
        $this->deleteByOrg('products', $orgId);
        $this->deleteByOrg('ingredients', $orgId);
    }

    private function deleteByOrg(string $table, string $orgId): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'organization_id')) {
            return;
        }
        DB::table($table)->where('organization_id', $orgId)->delete();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $ids
     */
    private function deleteWhereIn(string $table, string $column, $ids): void
    {
        if (! Schema::hasTable($table) || $ids->isEmpty()) {
            return;
        }
        DB::table($table)->whereIn($column, $ids->all())->delete();
    }
}
