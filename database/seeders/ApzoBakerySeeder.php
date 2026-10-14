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
use App\Modules\Api\V1\Recipe\Models\Recipe;
use App\Modules\Api\V1\User\Models\User;
use App\Modules\Api\V1\Vendor\Models\Vendor;
use App\Services\DefaultStaffProfilesService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ApzoBakerySeeder extends Seeder
{
    private const ORG_NAME = "APZO bakery";
    private const ORG_EMAIL = "nathprem529@gmail.com";
    private const ADMIN_EMAIL = "nathprem529@gmail.com";
    private const ADMIN_PASSWORD = "Prem@123";

    public function run(): void
    {
        $this->command?->info("Seeding APZO bakery Organization, User & All Modules...");

        $stockService = app(BranchTransferStockService::class);
        $billingStock = app(BillingStockService::class);

        DB::transaction(function () use ($stockService, $billingStock) {
            // 1. Organization
            $org = Organization::withoutGlobalScopes()->updateOrCreate(
                ["name" => self::ORG_NAME],
                [
                    "email" => self::ORG_EMAIL,
                    "description" => "APZO bakery Main Production Chain",
                    "phone" => "+919876543210",
                    "address" => "45 Baker Street, Central Kitchen, Chennai, Tamil Nadu 600001",
                ]
            );

            // 2. Admin User
            $admin = User::withoutGlobalScopes()->updateOrCreate(
                ["email" => self::ADMIN_EMAIL],
                [
                    "organization_id" => $org->id,
                    "first_name" => "Nath",
                    "last_name" => "Prem",
                    "password" => Hash::make(self::ADMIN_PASSWORD),
                    "role" => "admin",
                    "phone" => "+919876543210",
                    "is_active" => 1,
                ]
            );

            Auth::login($admin);

            // 3. Branches
            $warehouse = $this->upsertBranch($org->id, "APZO Main Warehouse", "warehouse", "Industrial Estate, Guindy, Chennai", "+91442800001");
            $centralOutlet = $this->upsertBranch($org->id, "APZO Central Bakery Outlet", "retail", "T Nagar, Main Commercial Street, Chennai", "+91442800002");
            $westOutlet = $this->upsertBranch($org->id, "APZO West Coast Branch", "retail", "Anna Nagar West, Chennai", "+91442800003");

            $admin->branch_id = $warehouse->id;
            $admin->save();

            // Staff Profiles setup
            app(DefaultStaffProfilesService::class)->ensureForOrganization((string) $org->id, (string) $admin->id);

            // 4. Vendors
            $vendors = $this->seedVendors($org->id);

            // 5. Ingredients & Stock Setup
            [$ingredients, $ingredientTargets] = $this->seedIngredients($org->id, $vendors);
            $this->ensureIngredientStock($org->id, $ingredients, $ingredientTargets);

            // 6. Products & BOM Recipes
            [$products, $productTargets] = $this->seedProducts($org->id);
            $this->seedRecipes($products, $ingredients);

            // 7. Material Issues & Production Batches
            $this->seedMaterialIssue($org->id, $ingredients, $admin->id);
            $this->seedProductionBatches($org->id, $products, $productTargets, $admin->id);

            // 8. Branch Transfers (Warehouse -> Outlets)
            $this->seedTransfers($org->id, $admin->id, $stockService, $products, $centralOutlet, $westOutlet);

            // 9. Billing / POS Sales (at Central Outlet)
            $this->seedBills($org->id, $centralOutlet->id, $products, $billingStock);

            $this->command?->info("APZO bakery successfully seeded!");
            $this->command?->info("  Organization: ".self::ORG_NAME);
            $this->command?->info("  User: ".self::ADMIN_EMAIL." / ".self::ADMIN_PASSWORD);
            $this->command?->info("  Branches: ".$warehouse->name.", ".$centralOutlet->name.", ".$westOutlet->name);
        });
    }

    private function upsertBranch(string $orgId, string $name, string $type, string $address, string $phone): Branch
    {
        return Branch::withoutGlobalScopes()->updateOrCreate(
            ["organization_id" => $orgId, "name" => $name],
            ["type" => $type, "address" => $address, "phone" => $phone]
        );
    }

    private function seedVendors(string $orgId): array
    {
        $defs = [
            "APZO Flour Suppliers" => [
                "contact_person" => "Ramesh Kumar",
                "phone" => "+919840111222",
                "email" => "ramesh@apzoflour.com",
                "address" => "Industrial Area, Chennai",
            ],
            "APZO Dairy Products" => [
                "contact_person" => "Suresh Dairy",
                "phone" => "+919840222333",
                "email" => "sales@apzodairy.com",
                "address" => "Milk Colony Road, Chennai",
            ],
            "APZO Sugar Mills" => [
                "contact_person" => "Priya Sugar",
                "phone" => "+919840333444",
                "email" => "orders@apzosugar.com",
                "address" => "Sugar Mill Road, Trichy",
            ],
            "APZO Baking Essentials" => [
                "contact_person" => "Anil Baker",
                "phone" => "+919840444555",
                "email" => "info@apzobaking.com",
                "address" => "Trade Centre, Guindy, Chennai",
            ],
            "APZO Packaging Solutions" => [
                "contact_person" => "Mohan Pack",
                "phone" => "+919840555666",
                "email" => "supply@apzopack.com",
                "address" => "Ambattur Estate, Chennai",
            ],
        ];

        $out = [];
        foreach ($defs as $name => $data) {
            $out[$name] = Vendor::withoutGlobalScopes()->updateOrCreate(
                ["organization_id" => $orgId, "name" => $name],
                $data
            );
        }

        return $out;
    }

    private function seedIngredients(string $orgId, array $vendors): array
    {
        $defs = [
            "Wheat Flour" => ["unit" => "gm", "vendor" => "APZO Flour Suppliers", "min" => 50000, "target" => 500000],
            "All Purpose Maida" => ["unit" => "gm", "vendor" => "APZO Flour Suppliers", "min" => 100000, "target" => 1000000],
            "White Sugar" => ["unit" => "gm", "vendor" => "APZO Sugar Mills", "min" => 50000, "target" => 500000],
            "Unsalted Butter" => ["unit" => "gm", "vendor" => "APZO Dairy Products", "min" => 30000, "target" => 300000],
            "Fresh Farm Milk" => ["unit" => "ml", "vendor" => "APZO Dairy Products", "min" => 50000, "target" => 500000],
            "Fresh Cream" => ["unit" => "ml", "vendor" => "APZO Dairy Products", "min" => 20000, "target" => 200000],
            "Active Dry Yeast" => ["unit" => "gm", "vendor" => "APZO Baking Essentials", "min" => 5000, "target" => 50000],
            "Dark Chocolate Chips" => ["unit" => "gm", "vendor" => "APZO Baking Essentials", "min" => 20000, "target" => 200000],
            "Dutch Process Cocoa Powder" => ["unit" => "gm", "vendor" => "APZO Baking Essentials", "min" => 10000, "target" => 100000],
            "Pure Vanilla Extract" => ["unit" => "ml", "vendor" => "APZO Baking Essentials", "min" => 5000, "target" => 20000],
            "Baking Powder" => ["unit" => "gm", "vendor" => "APZO Baking Essentials", "min" => 5000, "target" => 30000],
        ];

        $out = [];
        $targets = [];
        foreach ($defs as $name => $d) {
            $out[$name] = Ingredient::withoutGlobalScopes()->updateOrCreate(
                ["organization_id" => $orgId, "name" => $name],
                [
                    "vendor_id" => $vendors[$d["vendor"]]->id,
                    "unit" => $d["unit"],
                    "minimum_stock_level" => $d["min"],
                ]
            );
            $targets[$name] = (float) $d["target"];
        }

        return [$out, $targets];
    }

    private function ensureIngredientStock(string $orgId, array $ingredients, array $targets): void
    {
        foreach ($ingredients as $name => $ing) {
            $target = (float) ($targets[$name] ?? 0);
            $current = (float) ($ing->fresh()->current_stock ?? 0);
            $need = $target - $current;
            if ($need <= 0) {
                continue;
            }

            $tx = new InventoryTransaction();
            $tx->organization_id = $orgId;
            $tx->ingredient_id = $ing->id;
            $tx->type = "in";
            $tx->quantity = $need;
            $tx->reference_note = "Initial Stock Seed for APZO bakery " . $name;
            $tx->save();

            $ing->current_stock = $current + $need;
            $ing->save();
        }
    }

    private function seedProducts(string $orgId): array
    {
        $svgBread = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='200' height='200' viewBox='0 0 200 200'><rect width='200' height='200' fill='%23fef3c7'/><path d='M40,110 Q40,60 100,60 Q160,60 160,110 L160,140 Q160,150 150,150 L50,150 Q40,150 40,140 Z' fill='%23d97706'/><path d='M50,110 L150,110' stroke='%23b45309' stroke-width='4'/><text x='100' y='185' font-size='16' font-weight='bold' text-anchor='middle' fill='%2392400e'>Fresh Bread</text></svg>";

        $svgCake = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='200' height='200' viewBox='0 0 200 200'><rect width='200' height='200' fill='%23fce7f3'/><path d='M40,140 L160,140 L150,80 L50,80 Z' fill='%23831843'/><rect x='35' y='140' width='130' height='20' rx='5' fill='%23be185d'/><circle cx='100' cy='65' r='12' fill='%23dc2626'/><text x='100' y='185' font-size='16' font-weight='bold' text-anchor='middle' fill='%23831843'>Truffle Cake</text></svg>";

        $svgCroissant = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='200' height='200' viewBox='0 0 200 200'><rect width='200' height='200' fill='%23fffbeb'/><path d='M50,120 Q100,50 150,120 Q120,150 50,120 Z' fill='%23f59e0b'/><path d='M70,110 Q100,70 130,110' stroke='%23d97706' stroke-width='4' fill='none'/><text x='100' y='185' font-size='16' font-weight='bold' text-anchor='middle' fill='%23b45309'>Butter Croissant</text></svg>";

        $svgCupcake = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='200' height='200' viewBox='0 0 200 200'><rect width='200' height='200' fill='%23fae8ff'/><path d='M60,110 L140,110 L130,160 L70,160 Z' fill='%23a21caf'/><path d='M55,110 Q55,70 100,70 Q145,70 145,110 Z' fill='%23e879f9'/><circle cx='100' cy='60' r='10' fill='%23ef4444'/><text x='100' y='185' font-size='16' font-weight='bold' text-anchor='middle' fill='%23701a75'>Cupcake</text></svg>";

        $svgCookie = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='200' height='200' viewBox='0 0 200 200'><rect width='200' height='200' fill='%23fef8ee'/><circle cx='100' cy='105' r='55' fill='%23d97706'/><circle cx='85' cy='85' r='6' fill='%23451a03'/><circle cx='120' cy='95' r='7' fill='%23451a03'/><circle cx='95' cy='125' r='6' fill='%23451a03'/><circle cx='125' cy='120' r='5' fill='%23451a03'/><text x='100' y='185' font-size='16' font-weight='bold' text-anchor='middle' fill='%2378350f'>Choco Cookies</text></svg>";

        $svgRoll = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='200' height='200' viewBox='0 0 200 200'><rect width='200' height='200' fill='%23ecfdf5'/><ellipse cx='100' cy='105' rx='65' ry='40' fill='%2310b981'/><ellipse cx='100' cy='98' rx='60' ry='35' fill='%23f59e0b'/><text x='100' y='185' font-size='16' font-weight='bold' text-anchor='middle' fill='%23065f46'>Paneer Roll</text></svg>";

        $defs = [
            "7001" => [
                "name" => "Fresh Milk Sandwich Bread",
                "category" => "bread",
                "unit" => "pcs",
                "price" => 45.00,
                "target" => 200,
                "image" => $svgBread,
            ],
            "7002" => [
                "name" => "Chocolate Truffle Cake 1kg",
                "category" => "cake",
                "unit" => "pcs",
                "price" => 750.00,
                "target" => 50,
                "image" => $svgCake,
            ],
            "7003" => [
                "name" => "French Butter Croissant",
                "category" => "snack",
                "unit" => "pcs",
                "price" => 65.00,
                "target" => 150,
                "image" => $svgCroissant,
            ],
            "7004" => [
                "name" => "Velvet Chocolate Cupcake",
                "category" => "cake",
                "unit" => "pcs",
                "price" => 80.00,
                "target" => 200,
                "image" => $svgCupcake,
            ],
            "7005" => [
                "name" => "Crunchy Choco Chip Cookies Pack",
                "category" => "biscuit",
                "unit" => "pcs",
                "price" => 140.00,
                "target" => 100,
                "image" => $svgCookie,
            ],
            "7006" => [
                "name" => "Paneer Soft Roll",
                "category" => "snack",
                "unit" => "pcs",
                "price" => 40.00,
                "target" => 120,
                "image" => $svgRoll,
            ],
        ];

        $out = [];
        $targets = [];
        foreach ($defs as $num => $d) {
            $name = $d["name"];
            $existing = Product::withoutGlobalScopes()
                ->where("organization_id", $orgId)
                ->where("product_number", $num)
                ->first();

            if ($existing) {
                DB::table("products")->where("id", $existing->id)->update([
                    "name" => $name,
                    "description" => "APZO bakery " . $name,
                    "price" => $d["price"],
                    "unit" => $d["unit"],
                    "category" => $d["category"],
                    "product_image" => $d["image"],
                    "status" => "active",
                    "updated_at" => now(),
                ]);
                $p = Product::withoutGlobalScopes()->findOrFail($existing->id);
            } else {
                $id = (string) \Illuminate\Support\Str::uuid();
                DB::table("products")->insert([
                    "id" => $id,
                    "organization_id" => $orgId,
                    "product_number" => $num,
                    "name" => $name,
                    "description" => "APZO bakery " . $name,
                    "price" => $d["price"],
                    "unit" => $d["unit"],
                    "category" => $d["category"],
                    "product_image" => $d["image"],
                    "status" => "active",
                    "created_at" => now(),
                    "updated_at" => now(),
                ]);
                $p = Product::withoutGlobalScopes()->findOrFail($id);
            }

            $out[$name] = $p;
            $targets[$name] = (float) $d["target"];
        }

        return [$out, $targets];
    }

    private function seedRecipes(array $products, array $ingredients): void
    {
        $recipes = [
            "Fresh Milk Sandwich Bread" => [
                ["ing" => $ingredients["All Purpose Maida"], "qty" => 400],
                ["ing" => $ingredients["White Sugar"], "qty" => 30],
                ["ing" => $ingredients["Unsalted Butter"], "qty" => 40],
                ["ing" => $ingredients["Fresh Farm Milk"], "qty" => 150],
                ["ing" => $ingredients["Active Dry Yeast"], "qty" => 10],
            ],
            "Chocolate Truffle Cake 1kg" => [
                ["ing" => $ingredients["All Purpose Maida"], "qty" => 300],
                ["ing" => $ingredients["White Sugar"], "qty" => 250],
                ["ing" => $ingredients["Unsalted Butter"], "qty" => 200],
                ["ing" => $ingredients["Fresh Cream"], "qty" => 300],
                ["ing" => $ingredients["Dutch Process Cocoa Powder"], "qty" => 100],
                ["ing" => $ingredients["Dark Chocolate Chips"], "qty" => 150],
                ["ing" => $ingredients["Pure Vanilla Extract"], "qty" => 20],
            ],
            "French Butter Croissant" => [
                ["ing" => $ingredients["All Purpose Maida"], "qty" => 200],
                ["ing" => $ingredients["Unsalted Butter"], "qty" => 120],
                ["ing" => $ingredients["White Sugar"], "qty" => 20],
                ["ing" => $ingredients["Fresh Farm Milk"], "qty" => 50],
                ["ing" => $ingredients["Active Dry Yeast"], "qty" => 5],
            ],
            "Velvet Chocolate Cupcake" => [
                ["ing" => $ingredients["All Purpose Maida"], "qty" => 80],
                ["ing" => $ingredients["White Sugar"], "qty" => 60],
                ["ing" => $ingredients["Unsalted Butter"], "qty" => 50],
                ["ing" => $ingredients["Dutch Process Cocoa Powder"], "qty" => 30],
                ["ing" => $ingredients["Dark Chocolate Chips"], "qty" => 20],
            ],
            "Crunchy Choco Chip Cookies Pack" => [
                ["ing" => $ingredients["All Purpose Maida"], "qty" => 150],
                ["ing" => $ingredients["Unsalted Butter"], "qty" => 100],
                ["ing" => $ingredients["White Sugar"], "qty" => 80],
                ["ing" => $ingredients["Dark Chocolate Chips"], "qty" => 80],
            ],
            "Paneer Soft Roll" => [
                ["ing" => $ingredients["All Purpose Maida"], "qty" => 120],
                ["ing" => $ingredients["Unsalted Butter"], "qty" => 30],
                ["ing" => $ingredients["Fresh Farm Milk"], "qty" => 40],
                ["ing" => $ingredients["Active Dry Yeast"], "qty" => 5],
            ],
        ];

        foreach ($recipes as $prodName => $items) {
            $product = $products[$prodName] ?? null;
            if (!$product) continue;

            foreach ($items as $item) {
                $ing = $item["ing"];
                Recipe::withoutGlobalScopes()->updateOrCreate(
                    [
                        "product_id" => $product->id,
                        "ingredient_id" => $ing->id,
                    ],
                    [
                        "quantity_required" => $item["qty"],
                    ]
                );
            }
        }
    }

    private function seedMaterialIssue(string $orgId, array $ingredients, string $adminId): void
    {
        $issue = MaterialIssue::withoutGlobalScopes()
            ->where("organization_id", $orgId)
            ->where("notes", "APZO-BATCH-ISSUE-001")
            ->first();

        if ($issue) return;

        $issue = new MaterialIssue();
        $issue->organization_id = $orgId;
        $issue->issue_date = Carbon::now()->subDays(2)->toDateString();
        $issue->notes = "APZO-BATCH-ISSUE-001";
        $issue->created_by = $adminId;
        $issue->save();

        $items = [
            ["ing" => $ingredients["All Purpose Maida"], "qty" => 50000],
            ["ing" => $ingredients["White Sugar"], "qty" => 20000],
            ["ing" => $ingredients["Unsalted Butter"], "qty" => 15000],
        ];

        foreach ($items as $it) {
            $ing = $it["ing"];
            $row = new MaterialIssueItem();
            $row->organization_id = $orgId;
            $row->material_issue_id = $issue->id;
            $row->ingredient_id = $ing->id;
            $row->quantity = $it["qty"];
            $row->unit = $ing->unit;
            $row->save();

            $ingModel = $ing->fresh();
            if ($ingModel) {
                $ingModel->current_stock = max(0, (float)$ingModel->current_stock - $it["qty"]);
                $ingModel->save();
            }

            // Deduct ingredient stock for material issue
            InventoryTransaction::create([
                "organization_id" => $orgId,
                "ingredient_id" => $ing->id,
                "type" => "out",
                "quantity" => $it["qty"],
                "reference_note" => "Issued for APZO Baking Batch #001",
            ]);
        }
    }

    private function seedProductionBatches(string $orgId, array $products, array $targets, string $adminId): void
    {
        foreach ($products as $name => $prod) {
            $target = (float) ($targets[$name] ?? 100);
            $product = $prod->fresh();
            $current = (float) $product->current_stock;
            $need = $target - $current;
            if ($need > 0) {
                $product->current_stock = $current + $need;
                $product->save();
            }

            $batchNum = 'APZO-BATCH-' . $product->product_number;
            ProductionBatch::withoutGlobalScopes()->updateOrCreate(
                ['organization_id' => $orgId, 'batch_number' => $batchNum],
                [
                    'product_id' => $product->id,
                    'quantity_produced' => $target,
                    'pieces' => $product->unit === 'pcs' ? (int) $target : null,
                    'production_date' => Carbon::now()->toDateString(),
                    'status' => 'completed',
                    'notes' => 'APZO production for ' . $name,
                    'created_by' => $adminId,
                ]
            );

            // Ensure finished goods stock is in BranchStock for warehouse
            $whStock = DB::table("branch_stocks")
                ->where("organization_id", $orgId)
                ->where("product_id", $prod->id)
                ->first();

            if (!$whStock) {
                $whBranch = Branch::withoutGlobalScopes()
                    ->where("organization_id", $orgId)
                    ->where("type", "warehouse")
                    ->first();

                if ($whBranch) {
                    DB::table("branch_stocks")->insert([
                        "id" => (string) \Illuminate\Support\Str::uuid(),
                        "organization_id" => $orgId,
                        "branch_id" => $whBranch->id,
                        "product_id" => $prod->id,
                        "current_stock" => $target,
                        "created_at" => now(),
                        "updated_at" => now(),
                    ]);
                }
            }
        }
    }

    private function seedTransfers(
        string $orgId,
        string $adminId,
        BranchTransferStockService $stockService,
        array $products,
        Branch $centralOutlet,
        Branch $westOutlet
    ): void {
        $transferDefs = [
            [
                "notes" => "APZO-TRANSFER-001 Central Received",
                "branch" => $centralOutlet,
                "target" => BranchTransferStockService::STATUS_RECEIVED,
                "items" => [
                    ["product" => $products["Fresh Milk Sandwich Bread"], "qty" => 100],
                    ["product" => $products["Chocolate Truffle Cake 1kg"], "qty" => 20],
                    ["product" => $products["French Butter Croissant"], "qty" => 80],
                    ["product" => $products["Velvet Chocolate Cupcake"], "qty" => 100],
                    ["product" => $products["Crunchy Choco Chip Cookies Pack"], "qty" => 50],
                    ["product" => $products["Paneer Soft Roll"], "qty" => 60],
                ],
            ],
            [
                "notes" => "APZO-TRANSFER-002 West Dispatched",
                "branch" => $westOutlet,
                "target" => BranchTransferStockService::STATUS_DISPATCHED,
                "items" => [
                    ["product" => $products["Fresh Milk Sandwich Bread"], "qty" => 40],
                    ["product" => $products["French Butter Croissant"], "qty" => 30],
                ],
            ],
        ];

        foreach ($transferDefs as $def) {
            $existing = BranchTransfer::withoutGlobalScopes()
                ->where("organization_id", $orgId)
                ->where("notes", $def["notes"])
                ->first();

            if ($existing) {
                continue;
            }

            $transfer = new BranchTransfer();
            $transfer->organization_id = $orgId;
            $transfer->branch_id = $def["branch"]->id;
            $transfer->transfer_date = Carbon::now()->toDateString();
            $transfer->status = BranchTransferStockService::STATUS_PENDING;
            $transfer->notes = $def["notes"];
            $transfer->created_by = $adminId;
            $transfer->save();

            foreach ($def["items"] as $item) {
                $product = $item["product"];
                $row = new BranchTransferItem();
                $row->organization_id = $orgId;
                $row->branch_transfer_id = $transfer->id;
                $row->product_id = $product->id;
                $row->quantity = $item["qty"];
                $row->unit = $product->unit;
                $row->pieces = $item["qty"];
                $row->save();
            }

            if ($def["target"] === BranchTransferStockService::STATUS_DISPATCHED) {
                $stockService->transition($transfer->fresh(), BranchTransferStockService::STATUS_DISPATCHED);
            } elseif ($def["target"] === BranchTransferStockService::STATUS_RECEIVED) {
                $stockService->transition($transfer->fresh(), BranchTransferStockService::STATUS_DISPATCHED);
                $stockService->transition($transfer->fresh(), BranchTransferStockService::STATUS_RECEIVED);
            }
        }
    }

    private function seedBills(string $orgId, string $branchId, array $products, BillingStockService $billingStock): void
    {
        $bills = [
            [
                "number" => "APZO-BILL-1001",
                "customer" => "Walk-in Customer (Rohan)",
                "method" => "Cash",
                "items" => [
                    ["product" => $products["Fresh Milk Sandwich Bread"], "qty" => 2],
                    ["product" => $products["French Butter Croissant"], "qty" => 4],
                ],
            ],
            [
                "number" => "APZO-BILL-1002",
                "customer" => "Walk-in Customer (Priya)",
                "method" => "UPI",
                "items" => [
                    ["product" => $products["Chocolate Truffle Cake 1kg"], "qty" => 1],
                    ["product" => $products["Velvet Chocolate Cupcake"], "qty" => 3],
                ],
            ],
            [
                "number" => "APZO-BILL-1003",
                "customer" => "Corporate Party Order",
                "method" => "Card",
                "items" => [
                    ["product" => $products["Crunchy Choco Chip Cookies Pack"], "qty" => 5],
                    ["product" => $products["Paneer Soft Roll"], "qty" => 10],
                ],
            ],
        ];

        foreach ($bills as $billDef) {
            $exists = Billing::withoutGlobalScopes()
                ->where("organization_id", $orgId)
                ->where("bill_number", $billDef["number"])
                ->exists();

            if ($exists) {
                continue;
            }

            $priced = [];
            $subTotal = 0.0;
            foreach ($billDef["items"] as $it) {
                $product = $it["product"];
                $qty = (float) $it["qty"];
                $unitPrice = (float) $product->price;
                $total = BillingPriceService::lineTotal($qty, $unitPrice, $product->unit);
                $subTotal += $total;

                $priced[] = [
                    "productId" => $product->id,
                    "quantity" => $qty,
                    "unitPrice" => $unitPrice,
                    "totalPrice" => $total,
                    "unit" => $product->unit,
                    "category" => $product->category,
                    "product" => $product,
                ];
            }

            // Deduct stock for sale
            $billingStock->deductForSale($orgId, $branchId, $priced);

            $bill = new Billing();
            $bill->organization_id = $orgId;
            $bill->branch_id = $branchId;
            $bill->bill_number = $billDef["number"];
            $bill->customer_name = $billDef["customer"];
            $bill->sub_total = $subTotal;
            $bill->discount_amount = 0;
            $bill->tax_amount = 0;
            $bill->grand_total = $subTotal;
            $bill->payment_method = $billDef["method"];
            $bill->payment_status = "paid";
            $bill->billing_date = Carbon::now();
            $bill->save();

            foreach ($priced as $row) {
                $item = new BillingItem();
                $item->billing_id = $bill->id;
                $item->product_id = $row["productId"];
                $item->quantity = $row["quantity"];
                $item->unit_price = $row["unitPrice"];
                $item->total_price = $row["totalPrice"];
                $item->unit = $row["unit"];
                $item->category = $row["category"];
                $item->save();
            }
        }
    }
}
