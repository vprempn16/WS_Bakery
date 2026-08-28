<?php

namespace Database\Seeders;

use App\Modules\Api\V1\Billing\Models\Billing;
use App\Modules\Api\V1\Billing\Models\BillingItem;
use App\Modules\Api\V1\Branch\Models\Branch;
use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use App\Modules\Api\V1\BranchTransfer\Models\BranchTransfer;
use App\Modules\Api\V1\BranchTransfer\Models\BranchTransferItem;
use App\Modules\Api\V1\Ingredient\Models\Ingredient;
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

class BakeryTestDataSeeder extends Seeder
{
    public function run(): void
    {
        if ($this->command) {
            $this->command->info("Starting Bakery Test Data Seeding...");
        }

        // 1. Organization
        $org = Organization::firstOrCreate(
            ["email" => "contact@grandbakery.com"],
            [
                "name" => "Grand Bakery WMS",
                "phone" => "+919876543210",
                "address" => "123 Main Bazaar Road, Bangalore, Karnataka",
            ]
        );

        // 2. Admin & Staff Users
        $admin = User::updateOrCreate(
            ["email" => "admin@bakerywms.com"],
            [
                "organization_id" => $org->id,
                "first_name" => "Bakery",
                "last_name" => "Admin",
                "password" => Hash::make("password"),
                "role" => "admin",
                "is_active" => 1,
            ]
        );

        Auth::login($admin);

        // 3. Branches
        $warehouseBranch = $this->createBranch($org->id, "Central Kitchen & Warehouse", "warehouse", "Plot 45 Industrial Area, Bangalore", "+919876543211");
        $downtownBranch = $this->createBranch($org->id, "Downtown Retail Branch", "retail", "12 Commercial Street, Bangalore", "+919876543212");
        $mallBranch = $this->createBranch($org->id, "Mall Express Outlet", "retail", "L3 Phoenix Mall, Bangalore", "+919876543213");

        $admin->branch_id = $warehouseBranch->id;
        $admin->save();

        $warehouseManager = User::updateOrCreate(
            ["email" => "warehouse@grandbakery.com"],
            [
                "organization_id" => $org->id,
                "branch_id" => $warehouseBranch->id,
                "first_name" => "Suresh",
                "last_name" => "Kumar",
                "password" => Hash::make("password"),
                "role" => "warehouse",
                "is_active" => 1,
            ]
        );

        $downtownCashier = User::updateOrCreate(
            ["email" => "downtown.cashier@grandbakery.com"],
            [
                "organization_id" => $org->id,
                "branch_id" => $downtownBranch->id,
                "first_name" => "Ananya",
                "last_name" => "Rao",
                "password" => Hash::make("password"),
                "role" => "staff",
                "is_active" => 1,
            ]
        );

        $mallCashier = User::updateOrCreate(
            ["email" => "mall.cashier@grandbakery.com"],
            [
                "organization_id" => $org->id,
                "branch_id" => $mallBranch->id,
                "first_name" => "Rohan",
                "last_name" => "Verma",
                "password" => Hash::make("password"),
                "role" => "staff",
                "is_active" => 1,
            ]
        );

        // Ensure default staff profiles and roles exist
        app(DefaultStaffProfilesService::class)->ensureForOrganization((string) $org->id, (string) $admin->id);
        $this->assignRoleToUser((string) $org->id, $warehouseManager->id, "Warehouse");
        $this->assignRoleToUser((string) $org->id, $downtownCashier->id, "Sales");
        $this->assignRoleToUser((string) $org->id, $mallCashier->id, "Sales");

        // 4. Vendors
        $vendor1 = Vendor::updateOrCreate(
            ["organization_id" => $org->id, "name" => "Golden Grains Flour Mill"],
            ["contact_person" => "Ramesh Patel", "phone" => "+919811122233", "email" => "orders@goldengrains.com", "address" => "Grain Market, Mandya"]
        );
        $vendor2 = Vendor::updateOrCreate(
            ["organization_id" => $org->id, "name" => "Fresh Cream Dairy Co."],
            ["contact_person" => "Deepak Gowda", "phone" => "+919822233344", "email" => "supply@freshcreamdairy.com", "address" => "Dairy Circle, Kolar"]
        );
        $vendor3 = Vendor::updateOrCreate(
            ["organization_id" => $org->id, "name" => "Sweet Essence Sugar & Spices"],
            ["contact_person" => "Kavita Hegde", "phone" => "+919833344455", "email" => "sales@sweetessence.com", "address" => "APMC Market, Mysore"]
        );

        // 5. Ingredients (Valid unit picklists: pcs, ml, gm)
        $flour = Ingredient::updateOrCreate(
            ["organization_id" => $org->id, "name" => "All-Purpose Flour (Maida)"],
            ["vendor_id" => $vendor1->id, "unit" => "gm", "minimum_stock_level" => 50000, "current_stock" => 500000]
        );
        $wheatFlour = Ingredient::updateOrCreate(
            ["organization_id" => $org->id, "name" => "Whole Wheat Flour (Atta)"],
            ["vendor_id" => $vendor1->id, "unit" => "gm", "minimum_stock_level" => 50000, "current_stock" => 400000]
        );
        $sugar = Ingredient::updateOrCreate(
            ["organization_id" => $org->id, "name" => "Fine Refined Sugar"],
            ["vendor_id" => $vendor3->id, "unit" => "gm", "minimum_stock_level" => 30000, "current_stock" => 300000]
        );
        $butter = Ingredient::updateOrCreate(
            ["organization_id" => $org->id, "name" => "Unsalted Bakery Butter"],
            ["vendor_id" => $vendor2->id, "unit" => "gm", "minimum_stock_level" => 20000, "current_stock" => 150000]
        );
        $milk = Ingredient::updateOrCreate(
            ["organization_id" => $org->id, "name" => "Full Cream Whole Milk"],
            ["vendor_id" => $vendor2->id, "unit" => "ml", "minimum_stock_level" => 50000, "current_stock" => 200000]
        );
        $yeast = Ingredient::updateOrCreate(
            ["organization_id" => $org->id, "name" => "Active Dry Yeast"],
            ["vendor_id" => $vendor3->id, "unit" => "gm", "minimum_stock_level" => 1000, "current_stock" => 10000]
        );
        $cocoa = Ingredient::updateOrCreate(
            ["organization_id" => $org->id, "name" => "Dutch Process Cocoa Powder"],
            ["vendor_id" => $vendor3->id, "unit" => "gm", "minimum_stock_level" => 10000, "current_stock" => 50000]
        );

        // 6. Products (Valid picklists: pcs, gm, ml, bread, sweet, cake, snack, beverage, other, tier_1, tier_2, tier_3)
        $productsData = [
            ["num" => "PROD-001", "name" => "Sandwich White Bread", "cat" => "bread", "price" => 45.00, "unit" => "pcs", "tier" => "tier_1", "shelf" => 3, "stock" => 400],
            ["num" => "PROD-002", "name" => "Whole Wheat Bread", "cat" => "bread", "price" => 55.00, "unit" => "pcs", "tier" => "tier_1", "shelf" => 4, "stock" => 150],
            ["num" => "PROD-003", "name" => "Garlic Artisan Loaf", "cat" => "bread", "price" => 75.00, "unit" => "pcs", "tier" => "tier_2", "shelf" => 2, "stock" => 80],
            ["num" => "PROD-004", "name" => "Premium Kaju Katli", "cat" => "sweet", "price" => 850.00, "unit" => "gm", "tier" => "tier_3", "shelf" => 15, "stock" => 40],
            ["num" => "PROD-005", "name" => "Desi Ghee Gulab Jamun", "cat" => "sweet", "price" => 260.00, "unit" => "gm", "tier" => "tier_2", "shelf" => 7, "stock" => 60],
            ["num" => "PROD-006", "name" => "Special Motichoor Laddu", "cat" => "sweet", "price" => 400.00, "unit" => "gm", "tier" => "tier_2", "shelf" => 10, "stock" => 50],
            ["num" => "PROD-007", "name" => "Black Forest Cake 1kg", "cat" => "cake", "price" => 650.00, "unit" => "pcs", "tier" => "tier_2", "shelf" => 2, "stock" => 25],
            ["num" => "PROD-008", "name" => "Dutch Chocolate Cupcake", "cat" => "cake", "price" => 45.00, "unit" => "pcs", "tier" => "tier_1", "shelf" => 3, "stock" => 100],
            ["num" => "PROD-009", "name" => "Crispy Veg Puff", "cat" => "snack", "price" => 25.00, "unit" => "pcs", "tier" => "tier_1", "shelf" => 1, "stock" => 120],
            ["num" => "PROD-010", "name" => "French Butter Croissant", "cat" => "snack", "price" => 60.00, "unit" => "pcs", "tier" => "tier_2", "shelf" => 2, "stock" => 90],
            ["num" => "PROD-011", "name" => "Iced Cold Coffee", "cat" => "beverage", "price" => 90.00, "unit" => "pcs", "tier" => "tier_1", "shelf" => 1, "stock" => 150],
            ["num" => "PROD-012", "name" => "Bakery Special Spices Blend", "cat" => "spices", "price" => 120.00, "unit" => "gm", "tier" => "tier_1", "shelf" => 180, "stock" => 300],
        ];

        $products = [];
        foreach ($productsData as $p) {
            $products[$p["num"]] = Product::withoutGlobalScopes()->updateOrCreate(
                ["organization_id" => $org->id, "product_number" => $p["num"]],
                [
                    "name" => $p["name"],
                    "description" => "Freshly prepared " . $p["name"],
                    "category" => $p["cat"],
                    "price" => $p["price"],
                    "unit" => $p["unit"],
                    "tier" => $p["tier"],
                    "shelf_life_days" => $p["shelf"],
                    "current_stock" => $p["stock"],
                ]
            );
        }

        // 7. Recipes (Ingredients for ALL 12 Products)
        // PROD-001: Sandwich White Bread
        Recipe::updateOrCreate(["product_id" => $products["PROD-001"]->id, "ingredient_id" => $flour->id], ["quantity_required" => 350.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-001"]->id, "ingredient_id" => $yeast->id], ["quantity_required" => 5.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-001"]->id, "ingredient_id" => $sugar->id], ["quantity_required" => 20.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-001"]->id, "ingredient_id" => $butter->id], ["quantity_required" => 15.00]);

        // PROD-002: Whole Wheat Bread
        Recipe::updateOrCreate(["product_id" => $products["PROD-002"]->id, "ingredient_id" => $wheatFlour->id], ["quantity_required" => 380.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-002"]->id, "ingredient_id" => $yeast->id], ["quantity_required" => 5.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-002"]->id, "ingredient_id" => $sugar->id], ["quantity_required" => 15.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-002"]->id, "ingredient_id" => $butter->id], ["quantity_required" => 10.00]);

        // PROD-003: Garlic Artisan Loaf
        Recipe::updateOrCreate(["product_id" => $products["PROD-003"]->id, "ingredient_id" => $flour->id], ["quantity_required" => 300.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-003"]->id, "ingredient_id" => $yeast->id], ["quantity_required" => 5.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-003"]->id, "ingredient_id" => $butter->id], ["quantity_required" => 25.00]);

        // PROD-004: Premium Kaju Katli
        Recipe::updateOrCreate(["product_id" => $products["PROD-004"]->id, "ingredient_id" => $sugar->id], ["quantity_required" => 300.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-004"]->id, "ingredient_id" => $butter->id], ["quantity_required" => 50.00]);

        // PROD-005: Desi Ghee Gulab Jamun
        Recipe::updateOrCreate(["product_id" => $products["PROD-005"]->id, "ingredient_id" => $flour->id], ["quantity_required" => 100.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-005"]->id, "ingredient_id" => $sugar->id], ["quantity_required" => 400.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-005"]->id, "ingredient_id" => $milk->id], ["quantity_required" => 200.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-005"]->id, "ingredient_id" => $butter->id], ["quantity_required" => 50.00]);

        // PROD-006: Special Motichoor Laddu
        Recipe::updateOrCreate(["product_id" => $products["PROD-006"]->id, "ingredient_id" => $sugar->id], ["quantity_required" => 350.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-006"]->id, "ingredient_id" => $butter->id], ["quantity_required" => 40.00]);

        // PROD-007: Black Forest Cake 1kg
        Recipe::updateOrCreate(["product_id" => $products["PROD-007"]->id, "ingredient_id" => $flour->id], ["quantity_required" => 250.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-007"]->id, "ingredient_id" => $cocoa->id], ["quantity_required" => 80.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-007"]->id, "ingredient_id" => $sugar->id], ["quantity_required" => 200.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-007"]->id, "ingredient_id" => $butter->id], ["quantity_required" => 100.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-007"]->id, "ingredient_id" => $milk->id], ["quantity_required" => 150.00]);

        // PROD-008: Dutch Chocolate Cupcake
        Recipe::updateOrCreate(["product_id" => $products["PROD-008"]->id, "ingredient_id" => $flour->id], ["quantity_required" => 40.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-008"]->id, "ingredient_id" => $cocoa->id], ["quantity_required" => 15.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-008"]->id, "ingredient_id" => $sugar->id], ["quantity_required" => 30.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-008"]->id, "ingredient_id" => $butter->id], ["quantity_required" => 20.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-008"]->id, "ingredient_id" => $milk->id], ["quantity_required" => 25.00]);

        // PROD-009: Crispy Veg Puff
        Recipe::updateOrCreate(["product_id" => $products["PROD-009"]->id, "ingredient_id" => $flour->id], ["quantity_required" => 60.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-009"]->id, "ingredient_id" => $butter->id], ["quantity_required" => 30.00]);

        // PROD-010: French Butter Croissant
        Recipe::updateOrCreate(["product_id" => $products["PROD-010"]->id, "ingredient_id" => $flour->id], ["quantity_required" => 70.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-010"]->id, "ingredient_id" => $yeast->id], ["quantity_required" => 3.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-010"]->id, "ingredient_id" => $butter->id], ["quantity_required" => 40.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-010"]->id, "ingredient_id" => $sugar->id], ["quantity_required" => 10.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-010"]->id, "ingredient_id" => $milk->id], ["quantity_required" => 15.00]);

        // PROD-011: Iced Cold Coffee
        Recipe::updateOrCreate(["product_id" => $products["PROD-011"]->id, "ingredient_id" => $milk->id], ["quantity_required" => 250.00]);
        Recipe::updateOrCreate(["product_id" => $products["PROD-011"]->id, "ingredient_id" => $sugar->id], ["quantity_required" => 25.00]);

        // PROD-012: Bakery Special Spices Blend
        Recipe::updateOrCreate(["product_id" => $products["PROD-012"]->id, "ingredient_id" => $sugar->id], ["quantity_required" => 50.00]);

        // 8. Production Batches (expiry_timestamp)
        $this->createBatch($org->id, "BATCH-" . date("Ymd") . "-001", $products["PROD-001"]->id, 100, "completed", $admin->id);
        $this->createBatch($org->id, "BATCH-" . date("Ymd") . "-002", $products["PROD-007"]->id, 20, "completed", $admin->id);
        $this->createBatch($org->id, "BATCH-" . date("Ymd") . "-003", $products["PROD-010"]->id, 50, "in_progress", $admin->id);

        // 9. Branch Stocks
        $this->setBranchStock($org->id, $downtownBranch->id, $products["PROD-001"]->id, 40);
        $this->setBranchStock($org->id, $downtownBranch->id, $products["PROD-004"]->id, 15);
        $this->setBranchStock($org->id, $downtownBranch->id, $products["PROD-007"]->id, 8);
        $this->setBranchStock($org->id, $downtownBranch->id, $products["PROD-009"]->id, 50);
        $this->setBranchStock($org->id, $downtownBranch->id, $products["PROD-011"]->id, 30);

        $this->setBranchStock($org->id, $mallBranch->id, $products["PROD-001"]->id, 25);
        $this->setBranchStock($org->id, $mallBranch->id, $products["PROD-008"]->id, 40);
        $this->setBranchStock($org->id, $mallBranch->id, $products["PROD-010"]->id, 20);
        $this->setBranchStock($org->id, $mallBranch->id, $products["PROD-011"]->id, 40);

        // 10. Branch Transfers
        $this->createTransfer(
            $org->id,
            $downtownBranch->id,
            "TRN-" . date("Ymd") . "-001",
            "completed",
            "Morning stock dispatch to Downtown",
            $admin->id,
            [
                ["product" => $products["PROD-001"], "qty" => 50],
                ["product" => $products["PROD-007"], "qty" => 5],
            ]
        );

        $this->createTransfer(
            $org->id,
            $mallBranch->id,
            "TRN-" . date("Ymd") . "-002",
            "pending",
            "Afternoon stock dispatch to Mall Outlet",
            $admin->id,
            [
                ["product" => $products["PROD-008"], "qty" => 30],
                ["product" => $products["PROD-010"], "qty" => 20],
            ]
        );

        // 11. Billings & Items (Payment Enum: Cash, Card, UPI / Paid, Pending, Cancelled)
        $this->createBill(
            $org->id,
            $downtownBranch->id,
            "BILL-" . date("Ymd") . "-1001",
            "Rajesh Kumar",
            "+919988776655",
            "Cash",
            "Paid",
            [
                ["product" => $products["PROD-001"], "qty" => 2, "price" => 45.00],
                ["product" => $products["PROD-011"], "qty" => 1, "price" => 90.00],
            ],
            10.00,
            5.00
        );

        $this->createBill(
            $org->id,
            $mallBranch->id,
            "BILL-" . date("Ymd") . "-1002",
            "Priya Sharma",
            "+919977665544",
            "UPI",
            "Paid",
            [
                ["product" => $products["PROD-007"], "qty" => 1, "price" => 650.00],
                ["product" => $products["PROD-009"], "qty" => 2, "price" => 25.00],
            ],
            50.00,
            25.00
        );

        $this->createBill(
            $org->id,
            $downtownBranch->id,
            "BILL-" . date("Ymd") . "-1003",
            "Amit Patel",
            "+919966554433",
            "Card",
            "Pending",
            [
                ["product" => $products["PROD-004"], "qty" => 1, "price" => 850.00],
            ],
            0.00,
            0.00
        );

        if ($this->command) {
            $this->command->info("Bakery Test Data Seeding completed successfully!");
        }
    }

    private function createBranch(string $orgId, string $name, string $type, string $address, string $phone): Branch
    {
        $existing = Branch::withoutGlobalScopes()
            ->where("organization_id", $orgId)
            ->where("name", $name)
            ->first();

        if ($existing) {
            return $existing;
        }

        $b = new Branch();
        $b->organization_id = $orgId;
        $b->name = $name;
        $b->type = $type;
        $b->address = $address;
        $b->phone = $phone;
        $b->save();

        return $b;
    }

    private function assignRoleToUser(string $orgId, string $userId, string $roleName): void
    {
        $role = DB::table("roles")
            ->where("organization_id", $orgId)
            ->where("name", $roleName)
            ->where("deleted", 0)
            ->first();

        if (!$role) {
            return;
        }

        $exists = DB::table("role_user_rel")
            ->where("organization_id", $orgId)
            ->where("user_id", $userId)
            ->where("role_id", $role->id)
            ->exists();

        if (!$exists) {
            DB::table("role_user_rel")->insert([
                "role_id" => $role->id,
                "organization_id" => $orgId,
                "user_id" => $userId,
            ]);
        }
    }

    private function createBatch(string $orgId, string $batchNum, string $productId, float $qty, string $status, string $createdBy): void
    {
        ProductionBatch::withoutGlobalScopes()->updateOrCreate(
            ["organization_id" => $orgId, "batch_number" => $batchNum],
            [
                "product_id" => $productId,
                "quantity_produced" => $qty,
                "production_date" => Carbon::now()->toDateString(),
                "expiry_timestamp" => Carbon::now()->addDays(5),
                "status" => $status,
                "notes" => "Standard production batch " . $batchNum,
                "created_by" => $createdBy,
            ]
        );
    }

    private function setBranchStock(string $orgId, string $branchId, string $productId, float $stock): void
    {
        BranchStock::withoutGlobalScopes()->updateOrCreate(
            ["organization_id" => $orgId, "branch_id" => $branchId, "product_id" => $productId],
            ["current_stock" => $stock]
        );
    }

    private function createTransfer(string $orgId, string $branchId, string $transferNum, string $status, string $notes, string $createdBy, array $items): void
    {
        $transfer = BranchTransfer::withoutGlobalScopes()->updateOrCreate(
            ["organization_id" => $orgId, "transfer_number" => $transferNum],
            [
                "branch_id" => $branchId,
                "transfer_date" => Carbon::now()->toDateString(),
                "status" => $status,
                "notes" => $notes,
                "created_by" => $createdBy,
            ]
        );

        foreach ($items as $item) {
            BranchTransferItem::updateOrCreate(
                [
                    "organization_id" => $orgId,
                    "branch_transfer_id" => $transfer->id,
                    "product_id" => $item["product"]->id,
                ],
                [
                    "quantity" => $item["qty"],
                    "unit" => $item["product"]->unit,
                ]
            );
        }
    }

    private function createBill(
        string $orgId,
        string $branchId,
        string $billNum,
        string $custName,
        string $custPhone,
        string $method,
        string $status,
        array $items,
        float $discount,
        float $tax
    ): void {
        $subtotal = 0;
        foreach ($items as $it) {
            $subtotal += ($it["qty"] * $it["price"]);
        }

        $grandTotal = ($subtotal - $discount) + $tax;

        $bill = Billing::withoutGlobalScopes()->updateOrCreate(
            ["organization_id" => $orgId, "bill_number" => $billNum],
            [
                "branch_id" => $branchId,
                "customer_name" => $custName,
                "customer_phone" => $custPhone,
                "sub_total" => $subtotal,
                "discount_amount" => $discount,
                "tax_amount" => $tax,
                "grand_total" => $grandTotal,
                "payment_method" => $method,
                "payment_status" => $status,
                "billing_date" => Carbon::now(),
            ]
        );

        foreach ($items as $it) {
            BillingItem::updateOrCreate(
                [
                    "billing_id" => $bill->id,
                    "product_id" => $it["product"]->id,
                ],
                [
                    "quantity" => $it["qty"],
                    "unit_price" => $it["price"],
                    "total_price" => $it["qty"] * $it["price"],
                    "unit" => $it["product"]->unit,
                    "category" => $it["product"]->category,
                ]
            );
        }
    }
}
