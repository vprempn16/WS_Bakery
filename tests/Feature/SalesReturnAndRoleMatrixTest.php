<?php

namespace Tests\Feature;

use App\Modules\Api\V1\Branch\Models\Branch;
use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\SalesReturn\Models\SalesReturn;
use App\Modules\Api\V1\User\Models\User;
use App\Services\DefaultStaffProfilesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalesReturnAndRoleMatrixTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Branch $warehouse;
    private Branch $retail;
    private Product $product;
    private Product $productB;
    private User $admin;
    private User $warehouseUser;
    private User $salesUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['name' => 'Returns Bakery']);

        $this->warehouse = new Branch();
        $this->warehouse->organization_id = $this->org->id;
        $this->warehouse->name = 'Central Kitchen';
        $this->warehouse->type = 'warehouse';
        $this->warehouse->save();

        $this->retail = new Branch();
        $this->retail->organization_id = $this->org->id;
        $this->retail->name = 'Branch 2';
        $this->retail->type = 'retail';
        $this->retail->save();

        $this->product = new Product();
        $this->product->organization_id = $this->org->id;
        $this->product->name = 'Egg Puff';
        $this->product->price = 30;
        $this->product->unit = 'pcs';
        $this->product->status = 'active';
        $this->product->current_stock = 0;
        $this->product->save();

        $this->productB = new Product();
        $this->productB->organization_id = $this->org->id;
        $this->productB->name = 'Veg Puff';
        $this->productB->price = 25;
        $this->productB->unit = 'pcs';
        $this->productB->status = 'active';
        $this->productB->current_stock = 0;
        $this->productB->save();

        BranchStock::create([
            'organization_id' => $this->org->id,
            'branch_id' => $this->retail->id,
            'product_id' => $this->product->id,
            'current_stock' => 15,
        ]);

        BranchStock::create([
            'organization_id' => $this->org->id,
            'branch_id' => $this->retail->id,
            'product_id' => $this->productB->id,
            'current_stock' => 20,
        ]);

        $this->admin = User::create([
            'organization_id' => $this->org->id,
            'branch_id' => $this->warehouse->id,
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin-returns@example.com',
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);

        app(DefaultStaffProfilesService::class)->ensureForOrganization($this->org->id, $this->admin->id);

        $warehouseRoleId = \DB::table('roles')
            ->where('organization_id', $this->org->id)
            ->where('name', 'Warehouse')
            ->value('id');
        $salesRoleId = \DB::table('roles')
            ->where('organization_id', $this->org->id)
            ->where('name', 'Sales')
            ->value('id');

        $this->warehouseUser = User::create([
            'organization_id' => $this->org->id,
            'branch_id' => $this->warehouse->id,
            'first_name' => 'Wh',
            'last_name' => 'Staff',
            'email' => 'warehouse-returns@example.com',
            'role' => 'warehouse',
            'password' => Hash::make('password'),
        ]);

        $this->salesUser = User::create([
            'organization_id' => $this->org->id,
            'branch_id' => $this->retail->id,
            'first_name' => 'Retail',
            'last_name' => 'Staff',
            'email' => 'sales-returns@example.com',
            'role' => 'staff',
            'password' => Hash::make('password'),
        ]);

        \DB::table('role_user_rel')->insert([
            [
                'role_id' => $warehouseRoleId,
                'user_id' => $this->warehouseUser->id,
                'organization_id' => $this->org->id,
            ],
            [
                'role_id' => $salesRoleId,
                'user_id' => $this->salesUser->id,
                'organization_id' => $this->org->id,
            ],
        ]);
    }

    private function postReturn(array $payload)
    {
        static $n = 0;
        $n++;

        return $this->postJson('/api/v1/SalesReturn/new', $payload, [
            'Idempotency-Key' => 'sales-return-role-'.$n,
        ]);
    }

    public function test_warehouse_staff_cannot_access_sales_returns(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $this->getJson('/api/v1/SalesReturn')->assertStatus(403);
    }

    public function test_warehouse_staff_cannot_access_production_plans(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $this->getJson('/api/v1/ProductionPlan')->assertStatus(403);
    }

    public function test_sales_staff_can_create_return_and_stock_decreases(): void
    {
        Sanctum::actingAs($this->salesUser);

        $response = $this->postReturn( [
            'data' => [
                'values' => [
                    'branchId' => $this->retail->id,
                    'returnDate' => now()->format('Y-m-d'),
                    'notes' => 'Unsold puffs',
                ],
                'relatedRecords' => [
                    'items' => [
                        [
                            'productId' => $this->product->id,
                            'pieces' => 10,
                            'unit' => 'pcs',
                        ],
                    ],
                ],
            ],
        ], ['Idempotency-Key' => 'return-stock-down-1']);

        $response->assertStatus(201);
        $this->assertEquals(300.0, (float) $response->json('data.totalReturnValue'));
        $this->assertEquals(1, (int) $response->json('data.itemCount'));
        $this->assertNotEmpty($response->json('data.returnNumber'));

        $stock = BranchStock::where('branch_id', $this->retail->id)
            ->where('product_id', $this->product->id)
            ->first();

        $this->assertEquals(5.0, (float) $stock->current_stock);
        $this->assertEquals(1, SalesReturn::count());
        $this->assertEquals(1, SalesReturn::first()->items()->count());
    }

    public function test_multi_item_return_deducts_stock_for_each_product(): void
    {
        Sanctum::actingAs($this->salesUser);

        $response = $this->postReturn( [
            'data' => [
                'values' => [
                    'branchId' => $this->retail->id,
                    'returnDate' => now()->format('Y-m-d'),
                ],
                'relatedRecords' => [
                    'items' => [
                        [
                            'productId' => $this->product->id,
                            'pieces' => 5,
                            'unit' => 'pcs',
                        ],
                        [
                            'productId' => $this->productB->id,
                            'pieces' => 4,
                            'unit' => 'pcs',
                        ],
                    ],
                ],
            ],
        ], ['Idempotency-Key' => 'return-multi-1']);

        $response->assertStatus(201);
        // 5*30 + 4*25 = 150 + 100 = 250
        $this->assertEquals(250.0, (float) $response->json('data.totalReturnValue'));
        $this->assertEquals(2, (int) $response->json('data.itemCount'));

        $this->assertEquals(10.0, (float) BranchStock::where('product_id', $this->product->id)
            ->where('branch_id', $this->retail->id)->value('current_stock'));
        $this->assertEquals(16.0, (float) BranchStock::where('product_id', $this->productB->id)
            ->where('branch_id', $this->retail->id)->value('current_stock'));
    }

    public function test_insufficient_stock_rejects_return(): void
    {
        Sanctum::actingAs($this->salesUser);

        $response = $this->postReturn( [
            'data' => [
                'values' => [
                    'branchId' => $this->retail->id,
                    'returnDate' => now()->format('Y-m-d'),
                ],
                'relatedRecords' => [
                    'items' => [
                        [
                            'productId' => $this->product->id,
                            'pieces' => 100,
                            'unit' => 'pcs',
                        ],
                    ],
                ],
            ],
        ], ['Idempotency-Key' => 'return-insufficient-1']);

        $response->assertStatus(400);
        $this->assertEquals(15.0, (float) BranchStock::where('product_id', $this->product->id)
            ->where('branch_id', $this->retail->id)->value('current_stock'));
        $this->assertEquals(0, SalesReturn::count());
    }

    public function test_weight_product_return_uses_price_per_kg_like_pos(): void
    {
        Sanctum::actingAs($this->salesUser);

        $cake = new Product();
        $cake->organization_id = $this->org->id;
        $cake->name = 'Vanilla Cake';
        $cake->price = 450; // per kg
        $cake->unit = 'gm';
        $cake->status = 'active';
        $cake->current_stock = 0;
        $cake->save();

        BranchStock::create([
            'organization_id' => $this->org->id,
            'branch_id' => $this->retail->id,
            'product_id' => $cake->id,
            'current_stock' => 10000, // 10 kg in grams
        ]);

        $response = $this->postReturn( [
            'data' => [
                'values' => [
                    'branchId' => $this->retail->id,
                    'returnDate' => now()->format('Y-m-d'),
                ],
                'relatedRecords' => [
                    'items' => [
                        [
                            'productId' => $cake->id,
                            'quantity' => 1, // 1 gram
                            'unit' => 'gm',
                        ],
                    ],
                ],
            ],
        ], ['Idempotency-Key' => 'return-weight-1']);

        $response->assertStatus(201);
        // (1 / 1000) * 450 = 0.45
        $this->assertEquals(0.45, (float) $response->json('data.totalReturnValue'));
        $this->assertEquals(9999.0, (float) BranchStock::where('product_id', $cake->id)
            ->where('branch_id', $this->retail->id)->value('current_stock'));
    }

    public function test_list_returns_batch_with_total_return_value(): void
    {
        Sanctum::actingAs($this->salesUser);

        $this->postReturn( [
            'data' => [
                'values' => [
                    'branchId' => $this->retail->id,
                    'returnDate' => now()->format('Y-m-d'),
                ],
                'relatedRecords' => [
                    'items' => [
                        [
                            'productId' => $this->product->id,
                            'pieces' => 3,
                            'unit' => 'pcs',
                        ],
                    ],
                ],
            ],
        ], ['Idempotency-Key' => 'return-list-1'])->assertStatus(201);

        $list = $this->getJson('/api/v1/SalesReturn')->assertOk();
        $records = $list->json('data.records')
            ?? $list->json('data.data')
            ?? $list->json('data')
            ?? [];

        if (isset($records['data']) && is_array($records['data'])) {
            $records = $records['data'];
        }

        $this->assertNotEmpty($records);
        $first = is_array($records) ? ($records[0] ?? null) : null;
        if (is_array($first)) {
            $this->assertArrayHasKey('totalReturnValue', $first);
            $this->assertEquals(90.0, (float) $first['totalReturnValue']);
        }
    }

    public function test_admin_can_list_production_plans(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/ProductionPlan')->assertOk();
    }

    public function test_product_image_field_exists_in_module_config(): void
    {
        $fields = \App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig::getFields('products');
        $names = array_column($fields ?? [], 'fieldname');

        $this->assertContains('productImage', $names);
    }

    /**
     * P0: wastage must write off expired ledger qty (POS fresh gate must not block returns).
     * 200 on hand = 180 expired + 20 fresh → return 180 → stock 20, shelf Fresh.
     */
    public function test_return_expired_qty_succeeds_and_clears_shelf_to_fresh(): void
    {
        Sanctum::actingAs($this->salesUser);

        BranchStock::where('branch_id', $this->retail->id)
            ->where('product_id', $this->product->id)
            ->update(['current_stock' => 200]);

        $userId = (string) $this->admin->id;
        $orgId = (string) $this->org->id;
        $productId = (string) $this->product->id;

        \Illuminate\Support\Facades\DB::table('production_batches')->insert([
            [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'organization_id' => $orgId,
                'product_id' => $productId,
                'batch_number' => 'RET-OLD-180',
                'quantity_produced' => 180,
                'production_date' => now()->subDays(2)->toDateString(),
                'expiry_timestamp' => now()->subDay()->toDateTimeString(),
                'status' => 'completed',
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'organization_id' => $orgId,
                'product_id' => $productId,
                'batch_number' => 'RET-NEW-20',
                'quantity_produced' => 20,
                'production_date' => now()->toDateString(),
                'expiry_timestamp' => now()->addDays(2)->toDateTimeString(),
                'status' => 'completed',
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $before = \App\Services\ShelfLifeStatusService::statusForProducts(
            $orgId,
            [$productId],
            [$productId => 200.0]
        )[$productId];
        $this->assertSame(\App\Services\ShelfLifeStatusService::STATUS_EXPIRED, $before['shelfStatus']);
        $this->assertEquals(180.0, (float) $before['expiredQty']);

        // POS must reject selling more than fresh (20)
        try {
            app(\App\Modules\Api\V1\Billing\Services\BillingStockService::class)->deductForSale(
                $orgId,
                (string) $this->retail->id,
                [['productId' => $productId, 'quantity' => 50]]
            );
            $this->fail('Expected insufficient fresh stock for POS sale');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Insufficient fresh stock', $e->getMessage());
        }

        $response = $this->postReturn( [
            'data' => [
                'values' => [
                    'branchId' => $this->retail->id,
                    'returnDate' => now()->format('Y-m-d'),
                    'notes' => 'Expired puffs write-off',
                ],
                'relatedRecords' => [
                    'items' => [
                        [
                            'productId' => $this->product->id,
                            'pieces' => 180,
                            'unit' => 'pcs',
                        ],
                    ],
                ],
            ],
        ], ['Idempotency-Key' => 'return-expired-180']);

        $response->assertStatus(201);

        $stock = BranchStock::where('branch_id', $this->retail->id)
            ->where('product_id', $this->product->id)
            ->first();
        $this->assertEquals(20.0, (float) $stock->current_stock);

        $after = \App\Services\ShelfLifeStatusService::statusForProducts(
            $orgId,
            [$productId],
            [$productId => 20.0]
        )[$productId];
        $this->assertSame(\App\Services\ShelfLifeStatusService::STATUS_FRESH, $after['shelfStatus']);
        $this->assertEquals(0.0, (float) $after['expiredQty']);
    }
}
