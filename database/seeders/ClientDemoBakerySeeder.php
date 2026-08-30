<?php

namespace Database\Seeders;

use App\Modules\Api\V1\Billing\Models\Billing;
use App\Modules\Api\V1\Billing\Models\BillingItem;
use App\Modules\Api\V1\Billing\Services\BillingPriceService;
use App\Modules\Api\V1\Billing\Services\BillingStockService;
use App\Modules\Api\V1\Branch\Models\Branch;
use App\Modules\Api\V1\BranchTransfer\Models\BranchTransfer;
use App\Modules\Api\V1\BranchTransfer\Models\BranchTransferItem;
use App\Modules\Api\V1\BranchTransfer\Services\BranchTransferStockService;
use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use App\Modules\Api\V1\InventoryTransaction\Models\InventoryTransaction;
use App\Modules\Api\V1\MaterialIssue\Models\MaterialIssue;
use App\Modules\Api\V1\MaterialIssue\Models\MaterialIssueItem;
use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\ProductionBatch\Models\ProductionBatch;
use App\Modules\Api\V1\ProductionPlan\Models\ProductionPlan;
use App\Modules\Api\V1\ProductionPlan\Models\ProductionPlanItem;
use App\Modules\Api\V1\Recipe\Models\Recipe;
use App\Modules\Api\V1\User\Models\User;
use App\Modules\Api\V1\Vendor\Models\Vendor;
use App\Services\DefaultStaffProfilesService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Isolated Client Demo Bakery (Chennai) — idempotent.
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

    /** 30 laddus ≈ 1 kg → grams per piece */
    private const LADDU_GM_PER_PIECE = 1000 / 30;

    public function run(): void
    {
        $this->command?->info('Seeding Client Demo Bakery (isolated Chennai org)...');

        $stockService = app(BranchTransferStockService::class);
        $billingStock = app(BillingStockService::class);

        DB::transaction(function () use ($stockService, $billingStock) {
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

            $vendors = $this->seedVendors($org->id);
            [$ingredients, $ingredientTargets] = $this->seedIngredients($org->id, $vendors);
            $this->ensureIngredientStock($org->id, $ingredients, $ingredientTargets);
            [$products, $productTargets] = $this->seedProducts($org->id);
            $this->seedRecipes($products, $ingredients);
            $this->seedDemoMaterialIssue($org->id, $ingredients, $admin->id);
            $this->seedDemoProductionPlan($org->id, $products, $admin->id);
            $this->ensureWarehouseFinishedGoods($org->id, $products, $productTargets, $admin->id);

            // Transfers (lifecycle via stock service)
            $this->seedTransfers($org->id, $admin->id, $stockService, $products, $annaNagar, $tambaram, $velachery);
            $this->seedBills($org->id, $annaNagar->id, $products, $billingStock);

            $this->command?->info('Client Demo Bakery ready.');
            $this->command?->info('  Admin: '.self::ADMIN_EMAIL.' / '.self::ADMIN_PASSWORD);
            $this->command?->info('  Branches: '.$warehouse->name.', '.$chennaiMain->name.', '.$annaNagar->name.', '.$tambaram->name.', '.$velachery->name);
        });
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
     * @return array{0: array<string, Ingredient>, 1: array<string, float>}
     */
    private function seedIngredients(string $orgId, array $vendors): array
    {
        $defs = [
            'Maida' => ['unit' => 'gm', 'vendor' => 'Sri Lakshmi Foods', 'min' => 10000, 'target' => 100000],
            'Besan' => ['unit' => 'gm', 'vendor' => 'Sri Lakshmi Foods', 'min' => 5000, 'target' => 50000],
            'Sugar' => ['unit' => 'gm', 'vendor' => 'Sri Lakshmi Foods', 'min' => 8000, 'target' => 80000],
            'Rava' => ['unit' => 'gm', 'vendor' => 'Sri Lakshmi Foods', 'min' => 4000, 'target' => 40000],
            'Milk' => ['unit' => 'ml', 'vendor' => 'Fresh Dairy Suppliers', 'min' => 10000, 'target' => 100000],
            'Butter' => ['unit' => 'gm', 'vendor' => 'Fresh Dairy Suppliers', 'min' => 3000, 'target' => 30000],
            'Ghee' => ['unit' => 'gm', 'vendor' => 'Fresh Dairy Suppliers', 'min' => 2500, 'target' => 25000],
            'Cream' => ['unit' => 'ml', 'vendor' => 'Fresh Dairy Suppliers', 'min' => 4000, 'target' => 40000],
            'Cocoa Powder' => ['unit' => 'gm', 'vendor' => 'Baking Essentials India', 'min' => 1000, 'target' => 10000],
            'Baking Powder' => ['unit' => 'gm', 'vendor' => 'Baking Essentials India', 'min' => 500, 'target' => 5000],
            'Baking Soda' => ['unit' => 'gm', 'vendor' => 'Baking Essentials India', 'min' => 300, 'target' => 3000],
            'Vanilla Essence' => ['unit' => 'ml', 'vendor' => 'Baking Essentials India', 'min' => 500, 'target' => 5000],
            'Cashew' => ['unit' => 'gm', 'vendor' => 'Dry Fruits & Nuts Hub', 'min' => 2000, 'target' => 20000],
            'Almond' => ['unit' => 'gm', 'vendor' => 'Dry Fruits & Nuts Hub', 'min' => 1500, 'target' => 15000],
            'Raisins' => ['unit' => 'gm', 'vendor' => 'Dry Fruits & Nuts Hub', 'min' => 1500, 'target' => 15000],
            'Pistachio' => ['unit' => 'gm', 'vendor' => 'Dry Fruits & Nuts Hub', 'min' => 1000, 'target' => 10000],
        ];

        $out = [];
        $targets = [];
        foreach ($defs as $name => $d) {
            $out[$name] = Ingredient::withoutGlobalScopes()->updateOrCreate(
                ['organization_id' => $orgId, 'name' => $name],
                [
                    'vendor_id' => $vendors[$d['vendor']]->id,
                    'unit' => $d['unit'],
                    'minimum_stock_level' => $d['min'],
                ]
            );
            $targets[$name] = (float) $d['target'];
        }

        return [$out, $targets];
    }

    /**
     * @param  array<string, Ingredient>  $ingredients
     * @param  array<string, float>  $targets
     */
    private function ensureIngredientStock(string $orgId, array $ingredients, array $targets): void
    {
        foreach ($ingredients as $name => $ing) {
            $target = (float) ($targets[$name] ?? 0);
            $current = (float) $ing->fresh()->current_stock;
            $need = $target - $current;
            if ($need <= 0) {
                continue;
            }

            $tx = new InventoryTransaction();
            $tx->organization_id = $orgId;
            $tx->ingredient_id = $ing->id;
            $tx->type = 'in';
            $tx->quantity = $need;
            $tx->reference_note = 'DEMO-BAKERY-STOCK-IN '.$name;
            $tx->save();

            $ing->current_stock = $current + $need;
            $ing->save();
        }
    }

    /**
     * @return array{0: array<string, Product>, 1: array<string, float>}
     */
    private function seedProducts(string $orgId): array
    {
        $defs = [
            '9001' => ['name' => 'Chocolate Cake', 'cat' => 'cake', 'price' => 650, 'unit' => 'gm', 'shelf' => 48, 'tier' => 'tier_2', 'wh' => 20000],
            '9002' => ['name' => 'Vanilla Cake', 'cat' => 'cake', 'price' => 550, 'unit' => 'gm', 'shelf' => 48, 'tier' => 'tier_2', 'wh' => 20000],
            '9003' => ['name' => 'Black Forest Cake', 'cat' => 'cake', 'price' => 750, 'unit' => 'gm', 'shelf' => 48, 'tier' => 'tier_2', 'wh' => 15000],
            '9004' => ['name' => 'Red Velvet Cake', 'cat' => 'cake', 'price' => 800, 'unit' => 'gm', 'shelf' => 48, 'tier' => 'tier_2', 'wh' => 10000],
            '9005' => ['name' => 'Laddu', 'cat' => 'sweet', 'price' => 400, 'unit' => 'gm', 'shelf' => 168, 'tier' => 'tier_2', 'wh' => 10000],
            '9006' => ['name' => 'Mysore Pak', 'cat' => 'sweet', 'price' => 25, 'unit' => 'pcs', 'shelf' => 72, 'tier' => 'tier_2', 'wh' => 100],
            '9007' => ['name' => 'Gulab Jamun', 'cat' => 'sweet', 'price' => 15, 'unit' => 'pcs', 'shelf' => 48, 'tier' => 'tier_2', 'wh' => 200],
            '9008' => ['name' => 'Butter Bun', 'cat' => 'snack', 'price' => 20, 'unit' => 'pcs', 'shelf' => 24, 'tier' => 'tier_1', 'wh' => 150],
            '9009' => ['name' => 'Veg Puff', 'cat' => 'snack', 'price' => 25, 'unit' => 'pcs', 'shelf' => 12, 'tier' => 'tier_1', 'wh' => 200],
            '9010' => ['name' => 'Egg Puff', 'cat' => 'snack', 'price' => 30, 'unit' => 'pcs', 'shelf' => 12, 'tier' => 'tier_1', 'wh' => 150],
            '9011' => ['name' => 'Chicken Puff', 'cat' => 'snack', 'price' => 40, 'unit' => 'pcs', 'shelf' => 12, 'tier' => 'tier_1', 'wh' => 100],
            '9012' => ['name' => 'Cookies', 'cat' => 'snack', 'price' => 60, 'unit' => 'pcs', 'shelf' => 336, 'tier' => 'tier_2', 'wh' => 100],
        ];

        $out = [];
        $targets = [];
        foreach ($defs as $num => $d) {
            $existing = Product::withoutGlobalScopes()
                ->where('organization_id', $orgId)
                ->where('product_number', $num)
                ->first();

            if ($existing) {
                DB::table('products')->where('id', $existing->id)->update([
                    'name' => $d['name'],
                    'description' => 'DEMO-BAKERY '.$d['name'],
                    'price' => $d['price'],
                    'unit' => $d['unit'],
                    'category' => $d['cat'],
                    'status' => 'active',
                    'shelf_life' => $d['shelf'],
                    'tier' => $d['tier'],
                    'updated_at' => now(),
                ]);
                $p = Product::withoutGlobalScopes()->findOrFail($existing->id);
            } else {
                $id = (string) \Illuminate\Support\Str::uuid();
                DB::table('products')->insert([
                    'id' => $id,
                    'organization_id' => $orgId,
                    'product_number' => $num,
                    'name' => $d['name'],
                    'description' => 'DEMO-BAKERY '.$d['name'],
                    'price' => $d['price'],
                    'unit' => $d['unit'],
                    'category' => $d['cat'],
                    'status' => 'active',
                    'shelf_life' => $d['shelf'],
                    'tier' => $d['tier'],
                    'current_stock' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $p = Product::withoutGlobalScopes()->findOrFail($id);
            }

            $out[$d['name']] = $p;
            $targets[$d['name']] = (float) $d['wh'];
        }

        return [$out, $targets];
    }

    /**
     * Recipes: for gm products, quantity_required is per 1 gm finished product
     * (1kg recipe ÷ 1000). For pcs products, quantity_required is per piece.
     *
     * @param  array<string, Product>  $products
     * @param  array<string, Ingredient>  $ingredients
     */
    private function seedRecipes(array $products, array $ingredients): void
    {
        $perKg = fn (float $gmPerKg) => round($gmPerKg / 1000, 2);

        $map = [
            'Chocolate Cake' => [
                ['Maida', $perKg(400)],
                ['Sugar', $perKg(250)],
                ['Butter', $perKg(150)],
                ['Milk', $perKg(150)],
                ['Cocoa Powder', $perKg(40)],
                ['Baking Powder', $perKg(8)],
                ['Vanilla Essence', $perKg(5)],
            ],
            'Vanilla Cake' => [
                ['Maida', $perKg(450)],
                ['Sugar', $perKg(250)],
                ['Butter', $perKg(150)],
                ['Milk', $perKg(150)],
                ['Baking Powder', $perKg(8)],
                ['Vanilla Essence', $perKg(5)],
            ],
            'Black Forest Cake' => [
                ['Maida', $perKg(400)],
                ['Sugar', $perKg(250)],
                ['Butter', $perKg(120)],
                ['Milk', $perKg(150)],
                ['Cocoa Powder', $perKg(50)],
                ['Cream', $perKg(100)],
                ['Baking Powder', $perKg(8)],
            ],
            'Red Velvet Cake' => [
                ['Maida', $perKg(420)],
                ['Sugar', $perKg(260)],
                ['Butter', $perKg(140)],
                ['Milk', $perKg(160)],
                ['Cocoa Powder', $perKg(20)],
                ['Baking Powder', $perKg(8)],
                ['Vanilla Essence', $perKg(5)],
            ],
            'Laddu' => [
                ['Besan', $perKg(500)],
                ['Sugar', $perKg(300)],
                ['Ghee', $perKg(150)],
                ['Cashew', $perKg(30)],
                ['Raisins', $perKg(20)],
            ],
            'Mysore Pak' => [
                ['Besan', 40],
                ['Sugar', 50],
                ['Ghee', 30],
            ],
            'Gulab Jamun' => [
                ['Maida', 8],
                ['Sugar', 25],
                ['Ghee', 5],
                ['Milk', 15],
            ],
            'Butter Bun' => [
                ['Maida', 45],
                ['Sugar', 8],
                ['Butter', 10],
                ['Milk', 20],
            ],
            'Veg Puff' => [
                ['Maida', 40],
                ['Butter', 15],
            ],
            'Egg Puff' => [
                ['Maida', 40],
                ['Butter', 15],
            ],
            'Chicken Puff' => [
                ['Maida', 45],
                ['Butter', 18],
            ],
            'Cookies' => [
                ['Maida', 30],
                ['Sugar', 15],
                ['Butter', 20],
                ['Vanilla Essence', 1],
            ],
        ];

        foreach ($map as $productName => $rows) {
            $product = $products[$productName];
            $expectedIngredientIds = [];
            foreach ($rows as [$ingName, $qty]) {
                if ($qty < 0.01) {
                    $qty = 0.01;
                }
                $expectedIngredientIds[] = $ingredients[$ingName]->id;
                Recipe::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'ingredient_id' => $ingredients[$ingName]->id,
                    ],
                    ['quantity_required' => $qty]
                );
            }

            Recipe::where('product_id', $product->id)
                ->whereNotIn('ingredient_id', $expectedIngredientIds)
                ->delete();
        }
    }

    /**
     * Morning bulk raw-material take (stock OUT) — matches live Material Issue flow.
     *
     * @param  array<string, Ingredient>  $ingredients
     */
    private function seedDemoMaterialIssue(string $orgId, array $ingredients, string $adminId): void
    {
        $notes = 'DEMO-BAKERY morning material take';
        $existing = MaterialIssue::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('notes', $notes)
            ->first();
        if ($existing) {
            return;
        }

        $take = [
            'Maida' => 30000,
            'Sugar' => 15000,
            'Besan' => 8000,
            'Ghee' => 5000,
            'Butter' => 6000,
            'Milk' => 10000,
            'Rava' => 3000,
            'Cocoa Powder' => 2000,
        ];

        $issue = new MaterialIssue();
        $issue->organization_id = $orgId;
        $issue->issue_number = 'DEMO-ISSUE-001';
        $issue->issue_date = Carbon::now()->toDateString();
        $issue->notes = $notes;
        $issue->created_by = $adminId;
        $issue->status = 'posted';
        $issue->save();

        foreach ($take as $name => $quantity) {
            $ingredient = Ingredient::withoutGlobalScopes()
                ->where('organization_id', $orgId)
                ->where('id', $ingredients[$name]->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((float) $ingredient->current_stock < $quantity) {
                throw new \RuntimeException(
                    "Insufficient {$name} for demo material issue. Needed {$quantity}, have {$ingredient->current_stock}."
                );
            }

            $ingredient->current_stock = (float) $ingredient->current_stock - $quantity;
            $ingredient->save();

            InventoryTransaction::create([
                'organization_id' => $orgId,
                'ingredient_id' => $ingredient->id,
                'type' => 'out',
                'quantity' => $quantity,
                'reference_note' => "Material Issue: {$issue->issue_number}",
            ]);

            MaterialIssueItem::create([
                'organization_id' => $orgId,
                'material_issue_id' => $issue->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => $quantity,
                'unit' => $ingredient->unit,
            ]);
        }
    }

    /**
     * Tomorrow's plan (preview only — no stock writes).
     *
     * @param  array<string, Product>  $products
     */
    private function seedDemoProductionPlan(string $orgId, array $products, string $adminId): void
    {
        $notes = 'DEMO-BAKERY tomorrow production plan';
        $existing = ProductionPlan::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('notes', $notes)
            ->first();
        if ($existing) {
            return;
        }

        $plan = new ProductionPlan();
        $plan->organization_id = $orgId;
        $plan->plan_date = Carbon::tomorrow()->toDateString();
        $plan->status = 'approved';
        $plan->notes = $notes;
        $plan->created_by = $adminId;
        $plan->save();

        $items = [
            ['product' => $products['Veg Puff'], 'qty' => 200],
            ['product' => $products['Laddu'], 'qty' => 10000],
            ['product' => $products['Chocolate Cake'], 'qty' => 5000],
            ['product' => $products['Egg Puff'], 'qty' => 150],
        ];

        foreach ($items as $row) {
            ProductionPlanItem::create([
                'organization_id' => $orgId,
                'production_plan_id' => $plan->id,
                'product_id' => $row['product']->id,
                'planned_quantity' => $row['qty'],
            ]);
        }
    }

    /** @param array<string, Product> $products */
    private function ensureWarehouseFinishedGoods(string $orgId, array $products, array $productTargets, string $adminId): void
    {
        foreach ($products as $name => $product) {
            $target = (float) ($productTargets[$name] ?? 0);
            $product = $product->fresh();
            $current = (float) $product->current_stock;
            $need = $target - $current;
            if ($need < 0.01) {
                continue;
            }

            // Production batch adds finished goods only; raw materials were taken via Material Issue.
            $product->current_stock = $current + $need;
            $product->save();

            $batchNum = 'DEMO-BATCH-'.$product->product_number;
            ProductionBatch::withoutGlobalScopes()->updateOrCreate(
                ['organization_id' => $orgId, 'batch_number' => $batchNum],
                [
                    'product_id' => $product->id,
                    'quantity_produced' => $target,
                    'pieces' => $product->unit === 'pcs' ? (int) $target : null,
                    'production_date' => Carbon::now()->toDateString(),
                    'expiry_timestamp' => Carbon::now()->addHours((int) ($product->shelf_life ?: 48)),
                    'status' => 'completed',
                    'notes' => 'DEMO-BAKERY production for '.$name,
                    'created_by' => $adminId,
                ]
            );
        }
    }

    /**
     * @param  array<string, Product>  $products
     */
    private function seedTransfers(
        string $orgId,
        string $adminId,
        BranchTransferStockService $stockService,
        array $products,
        Branch $annaNagar,
        Branch $tambaram,
        Branch $velachery
    ): void {
        $ladduGm = round(50 * self::LADDU_GM_PER_PIECE, 2); // ~1666.67
        $laddu70 = round(70 * self::LADDU_GM_PER_PIECE, 2);
        $laddu40 = round(40 * self::LADDU_GM_PER_PIECE, 2);

        $defs = [
            [
                'notes' => 'DEMO-TRANSFER-001 Anna Nagar received',
                'branch' => $annaNagar,
                'target' => BranchTransferStockService::STATUS_RECEIVED,
                'items' => [
                    ['product' => $products['Laddu'], 'qty' => $ladduGm],
                    ['product' => $products['Veg Puff'], 'qty' => 40, 'pieces' => 40],
                    ['product' => $products['Egg Puff'], 'qty' => 30, 'pieces' => 30],
                    ['product' => $products['Cookies'], 'qty' => 15, 'pieces' => 15],
                ],
            ],
            [
                'notes' => 'DEMO-TRANSFER-002 Tambaram dispatched',
                'branch' => $tambaram,
                'target' => BranchTransferStockService::STATUS_DISPATCHED,
                'items' => [
                    ['product' => $products['Laddu'], 'qty' => $laddu70],
                    ['product' => $products['Chicken Puff'], 'qty' => 30, 'pieces' => 30],
                    ['product' => $products['Cookies'], 'qty' => 20, 'pieces' => 20],
                ],
            ],
            [
                'notes' => 'DEMO-TRANSFER-003 Velachery pending',
                'branch' => $velachery,
                'target' => BranchTransferStockService::STATUS_PENDING,
                'items' => [
                    ['product' => $products['Chocolate Cake'], 'qty' => 5000],
                    ['product' => $products['Vanilla Cake'], 'qty' => 5000],
                    ['product' => $products['Laddu'], 'qty' => $laddu40],
                ],
            ],
            [
                // Cancelled from pending — list demo only; no stock mutation
                'notes' => 'DEMO-TRANSFER-004 Anna Nagar cancelled',
                'branch' => $annaNagar,
                'target' => BranchTransferStockService::STATUS_CANCELLED,
                'items' => [
                    ['product' => $products['Cookies'], 'qty' => 5, 'pieces' => 5],
                ],
            ],
        ];

        foreach ($defs as $def) {
            $existing = BranchTransfer::withoutGlobalScopes()
                ->where('organization_id', $orgId)
                ->where('notes', $def['notes'])
                ->first();

            if ($existing) {
                $this->advanceTransferTo($existing, $def['target'], $stockService);
                continue;
            }

            $transfer = new BranchTransfer();
            $transfer->organization_id = $orgId;
            $transfer->branch_id = $def['branch']->id;
            $transfer->transfer_date = Carbon::now()->toDateString();
            $transfer->status = BranchTransferStockService::STATUS_PENDING;
            $transfer->notes = $def['notes'];
            $transfer->created_by = $adminId;
            $transfer->save();

            foreach ($def['items'] as $item) {
                /** @var Product $product */
                $product = $item['product'];
                $unit = strtolower(trim((string) $product->unit));
                $needsPieces = in_array($unit, ['pcs', 'pc', 'piece', 'pieces'], true);

                $row = new BranchTransferItem();
                $row->organization_id = $orgId;
                $row->branch_transfer_id = $transfer->id;
                $row->product_id = $product->id;
                $row->quantity = $item['qty'];
                $row->unit = $product->unit;
                $row->pieces = $needsPieces ? ($item['pieces'] ?? $item['qty']) : null;
                $row->save();
            }

            $this->advanceTransferTo($transfer->fresh(), $def['target'], $stockService);
        }
    }

    private function advanceTransferTo(
        BranchTransfer $transfer,
        string $target,
        BranchTransferStockService $stockService
    ): void {
        $status = $stockService->normalizeStatus($transfer->status);
        $target = $stockService->normalizeStatus($target);

        if ($status === $target) {
            return;
        }

        if ($status === BranchTransferStockService::STATUS_CANCELLED) {
            return;
        }

        if ($target === BranchTransferStockService::STATUS_DISPATCHED && $status === BranchTransferStockService::STATUS_PENDING) {
            $stockService->transition($transfer, BranchTransferStockService::STATUS_DISPATCHED);

            return;
        }

        if ($target === BranchTransferStockService::STATUS_RECEIVED) {
            if ($status === BranchTransferStockService::STATUS_PENDING) {
                $stockService->transition($transfer, BranchTransferStockService::STATUS_DISPATCHED);
                $transfer->refresh();
            }
            if ($stockService->normalizeStatus($transfer->status) === BranchTransferStockService::STATUS_DISPATCHED) {
                $stockService->transition($transfer, BranchTransferStockService::STATUS_RECEIVED);
            }

            return;
        }

        if ($target === BranchTransferStockService::STATUS_CANCELLED && $status === BranchTransferStockService::STATUS_PENDING) {
            $stockService->transition($transfer, BranchTransferStockService::STATUS_CANCELLED);
        }
    }

    /**
     * Paid POS bills on Anna Nagar after received transfer stock.
     *
     * @param  array<string, Product>  $products
     */
    private function seedBills(string $orgId, string $branchId, array $products, BillingStockService $billingStock): void
    {
        $bills = [
            [
                'number' => 'DEMO-BILL-001',
                'customer' => 'DEMO-BILL-001 Walk-in',
                'method' => 'Cash',
                'items' => [
                    // 5 laddus ≈ 166.67 gm
                    ['product' => $products['Laddu'], 'qty' => round(5 * self::LADDU_GM_PER_PIECE, 2)],
                    ['product' => $products['Veg Puff'], 'qty' => 2],
                ],
            ],
            [
                'number' => 'DEMO-BILL-002',
                'customer' => 'DEMO-BILL-002 Walk-in',
                'method' => 'UPI',
                'items' => [
                    ['product' => $products['Laddu'], 'qty' => round(10 * self::LADDU_GM_PER_PIECE, 2)],
                    ['product' => $products['Egg Puff'], 'qty' => 3],
                ],
            ],
            [
                'number' => 'DEMO-BILL-003',
                'customer' => 'DEMO-BILL-003 Walk-in',
                'method' => 'Card',
                'items' => [
                    ['product' => $products['Cookies'], 'qty' => 2],
                ],
            ],
            [
                'number' => 'DEMO-BILL-004',
                'customer' => 'DEMO-BILL-004 Walk-in',
                'method' => 'Cash',
                'items' => [
                    ['product' => $products['Egg Puff'], 'qty' => 3],
                    ['product' => $products['Laddu'], 'qty' => round(5 * self::LADDU_GM_PER_PIECE, 2)],
                ],
            ],
        ];

        foreach ($bills as $billDef) {
            $exists = Billing::withoutGlobalScopes()
                ->where('organization_id', $orgId)
                ->where('bill_number', $billDef['number'])
                ->exists();
            if ($exists) {
                continue;
            }

            $priced = [];
            $subTotal = 0.0;
            foreach ($billDef['items'] as $it) {
                /** @var Product $product */
                $product = $it['product'];
                $qty = (float) $it['qty'];
                $unitPrice = (float) $product->price;
                $total = BillingPriceService::lineTotal($qty, $unitPrice, $product->unit);
                $subTotal += $total;
                $priced[] = [
                    'productId' => $product->id,
                    'quantity' => $qty,
                    'unitPrice' => $unitPrice,
                    'totalPrice' => $total,
                    'unit' => $product->unit,
                    'category' => $product->category,
                    'product' => $product,
                ];
            }

            $billingStock->deductForSale($orgId, $branchId, $priced);

            $bill = new Billing();
            $bill->organization_id = $orgId;
            $bill->branch_id = $branchId;
            $bill->bill_number = $billDef['number'];
            $bill->customer_name = $billDef['customer'];
            $bill->sub_total = $subTotal;
            $bill->discount_amount = 0;
            $bill->tax_amount = 0;
            $bill->grand_total = $subTotal;
            $bill->payment_method = $billDef['method'];
            $bill->payment_status = 'Paid';
            $bill->billing_date = Carbon::now();
            $bill->save();

            foreach ($priced as $row) {
                $item = new BillingItem();
                $item->billing_id = $bill->id;
                $item->product_id = $row['productId'];
                $item->quantity = $row['quantity'];
                $item->unit_price = $row['unitPrice'];
                $item->total_price = $row['totalPrice'];
                $item->unit = $row['unit'];
                $item->category = $row['category'];
                $item->save();
            }
        }
    }
}
