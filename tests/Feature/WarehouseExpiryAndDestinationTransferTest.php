<?php

namespace Tests\Feature;

use App\Modules\Api\V1\Branch\Models\Branch;
use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\ProductionBatch\Models\ProductionBatch;
use App\Modules\Api\V1\Recipe\Models\Recipe;
use App\Modules\Api\V1\SalesReturn\Models\SalesReturn;
use App\Modules\Api\V1\User\Models\User;
use App\Services\WarehouseExpiredStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BakeryFixtures;
use Tests\TestCase;

class WarehouseExpiryAndDestinationTransferTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private User $salesUser;

    private Branch $warehouse;

    private Branch $branchA;

    private Branch $branchB;

    private Branch $branchC;

    private $product;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        WarehouseExpiredStockService::forgetCache();

        $this->org = Organization::create(['name' => 'Expiry Flow Bakery']);
        $this->admin = User::create([
            'organization_id' => $this->org->id,
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin-expiry-flow@example.com',
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);

        $this->warehouse = new Branch();
        $this->warehouse->organization_id = $this->org->id;
        $this->warehouse->name = 'Main Warehouse';
        $this->warehouse->type = 'warehouse';
        $this->warehouse->save();

        $this->branchA = new Branch();
        $this->branchA->organization_id = $this->org->id;
        $this->branchA->name = 'Branch A';
        $this->branchA->type = 'retail';
        $this->branchA->save();

        $this->branchB = new Branch();
        $this->branchB->organization_id = $this->org->id;
        $this->branchB->name = 'Branch B';
        $this->branchB->type = 'retail';
        $this->branchB->save();

        $this->branchC = new Branch();
        $this->branchC->organization_id = $this->org->id;
        $this->branchC->name = 'Branch C';
        $this->branchC->type = 'retail';
        $this->branchC->save();

        $this->admin->branch_id = $this->warehouse->id;
        $this->admin->save();

        $this->salesUser = User::create([
            'organization_id' => $this->org->id,
            'first_name' => 'Sales',
            'last_name' => 'Staff',
            'email' => 'sales-expiry-flow@example.com',
            'role' => 'sales',
            'branch_id' => $this->branchA->id,
            'password' => Hash::make('password'),
        ]);

        $this->product = BakeryFixtures::ownProduct((string) $this->org->id, [
            'name' => 'Veg Puff',
            'unit' => 'pcs',
            'shelf_life' => 6,
            'current_stock' => 0,
        ]);

        $flour = new Ingredient();
        $flour->organization_id = $this->org->id;
        $flour->name = 'Flour';
        $flour->unit = 'gm';
        $flour->minimum_stock_level = 0;
        $flour->current_stock = 5000;
        $flour->save();

        Recipe::create([
            'product_id' => $this->product->id,
            'ingredient_id' => $flour->id,
            'quantity_required' => 10,
        ]);

        Sanctum::actingAs($this->admin);
    }

    private function insertBatch(string $number, float $qty, $expiry, string $status = 'completed'): ProductionBatch
    {
        $batch = new ProductionBatch();
        $batch->organization_id = $this->org->id;
        $batch->product_id = $this->product->id;
        $batch->batch_number = $number;
        $batch->quantity_produced = $qty;
        $batch->pieces = (int) $qty;
        $batch->production_date = now()->subHours(8);
        $batch->expiry_timestamp = $expiry;
        $batch->status = $status;
        $batch->created_by = $this->admin->id;
        $batch->save();

        return $batch;
    }

    private function postTransfer(string $destinationId, float $qty, string $key)
    {
        return $this->postJson('/api/v1/BranchTransfer/new', [
            'data' => [
                'values' => [
                    'branchId' => $destinationId,
                    'transferDate' => now()->toDateString(),
                ],
                'relatedRecords' => [
                    'items' => [
                        [
                            'productId' => $this->product->id,
                            'quantity' => $qty,
                            'unit' => 'pcs',
                            'pieces' => $qty,
                        ],
                    ],
                ],
            ],
        ], ['Idempotency-Key' => $key]);
    }

    public function test_expired_warehouse_batch_cannot_transfer_then_dispose_clears_stock(): void
    {
        $batch = $this->insertBatch('VP-001', 100, now()->subHour());
        $this->product->current_stock = 100;
        $this->product->save();
        WarehouseExpiredStockService::forgetCache();

        $blocked = $this->postTransfer((string) $this->branchA->id, 50, 'wh-expire-block-'.uniqid());
        $blocked->assertStatus(400);
        $this->assertStringContainsString('Main Warehouse', $blocked->json('message') ?? '');
        $this->assertStringContainsString('expired stock remains', $blocked->json('message') ?? '');

        $dispose = $this->postJson("/api/v1/ProductionBatch/{$batch->id}/dispose-expired");
        $dispose->assertSuccessful();
        $this->assertEquals(0, SalesReturn::count());

        $batch->refresh();
        $this->assertSame('wasted', strtolower((string) $batch->status));
        $this->assertEquals(100.0, (float) $batch->wasted_quantity);
        $this->assertEquals(0.0, (float) $this->product->fresh()->current_stock);

        $after = $this->postTransfer((string) $this->branchA->id, 10, 'wh-expire-after-'.uniqid());
        $after->assertStatus(400);
        $this->assertStringContainsString('Insufficient warehouse stock', $after->json('message') ?? '');
    }

    public function test_fresh_batch_transfers_when_older_warehouse_batch_is_expired(): void
    {
        $this->insertBatch('VP-001', 100, now()->subHour());
        $this->insertBatch('VP-002', 100, now()->addHours(6));
        $this->product->current_stock = 200;
        $this->product->save();
        WarehouseExpiredStockService::forgetCache();

        $ok = $this->postTransfer((string) $this->branchA->id, 50, 'fresh-b-ok-'.uniqid());
        $ok->assertStatus(201);
        $this->assertEquals(200.0, (float) $this->product->fresh()->current_stock);
    }

    public function test_expired_stock_in_other_branches_does_not_block_transfer_to_clean_branch(): void
    {
        $this->insertBatch('VP-OLD', 35, now()->subHour());
        $this->insertBatch('VP-NEW', 100, now()->addHours(6));
        $this->product->current_stock = 100;
        $this->product->save();

        BranchStock::create([
            'organization_id' => $this->org->id,
            'branch_id' => $this->branchB->id,
            'product_id' => $this->product->id,
            'current_stock' => 20,
        ]);
        BranchStock::create([
            'organization_id' => $this->org->id,
            'branch_id' => $this->branchC->id,
            'product_id' => $this->product->id,
            'current_stock' => 15,
        ]);
        WarehouseExpiredStockService::forgetCache();

        $preview = $this->getJson(
            '/api/v1/BranchTransfer/destination-expiry?branchId='.$this->branchA->id
            .'&productIds='.$this->product->id
        );
        $preview->assertSuccessful();
        $this->assertSame('Branch A', $preview->json('data.branchName'));
        $this->assertEquals(0.0, (float) ($preview->json('data.products.0.expiredQty') ?? 0));

        $ok = $this->postTransfer((string) $this->branchA->id, 40, 'other-branches-ok-'.uniqid());
        $ok->assertStatus(201);
        $this->assertStringNotContainsString('Branch B', $ok->json('message') ?? '');
        $this->assertStringNotContainsString('Branch C', $ok->json('message') ?? '');
    }

    public function test_destination_expired_stock_blocks_transfer_and_names_that_branch_only(): void
    {
        $this->insertBatch('VP-OLD', 20, now()->subHour());
        $this->insertBatch('VP-NEW', 100, now()->addHours(6));
        $this->product->current_stock = 100;
        $this->product->save();

        BranchStock::create([
            'organization_id' => $this->org->id,
            'branch_id' => $this->branchA->id,
            'product_id' => $this->product->id,
            'current_stock' => 20,
        ]);
        BranchStock::create([
            'organization_id' => $this->org->id,
            'branch_id' => $this->branchB->id,
            'product_id' => $this->product->id,
            'current_stock' => 20,
        ]);
        WarehouseExpiredStockService::forgetCache();

        $blocked = $this->postTransfer((string) $this->branchA->id, 40, 'dest-a-block-'.uniqid());
        $blocked->assertStatus(400);
        $message = $blocked->json('message') ?? '';
        $this->assertStringContainsString('Branch A', $message);
        $this->assertStringContainsString('Process the expired stock from Branch A', $message);
        $this->assertStringNotContainsString('Branch B', $message);
        $this->assertStringNotContainsString('this branch', strtolower($message));
    }

    public function test_sales_return_rejects_warehouse_branch(): void
    {
        Sanctum::actingAs($this->admin);

        $res = $this->postJson('/api/v1/SalesReturn/new', [
            'data' => [
                'values' => [
                    'branchId' => $this->warehouse->id,
                    'returnDate' => now()->toDateString(),
                ],
                'relatedRecords' => [
                    'items' => [
                        [
                            'productId' => $this->product->id,
                            'quantity' => 1,
                            'unit' => 'pcs',
                            'pieces' => 1,
                        ],
                    ],
                ],
            ],
        ], ['Idempotency-Key' => 'wh-return-reject-'.uniqid()]);

        $res->assertStatus(422);
        $this->assertStringContainsString('Process Expired Stock', $res->json('message') ?? '');
        $this->assertEquals(0, SalesReturn::count());
    }

    public function test_retail_staff_cannot_dispose_warehouse_expired_stock(): void
    {
        $batch = $this->insertBatch('VP-LOCK', 10, now()->subHour());
        $this->product->current_stock = 10;
        $this->product->save();

        Sanctum::actingAs($this->salesUser);
        $res = $this->postJson("/api/v1/ProductionBatch/{$batch->id}/dispose-expired");
        $res->assertStatus(403);
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertEquals(10.0, (float) $this->product->fresh()->current_stock);
    }

    public function test_production_create_message_tells_user_to_transfer(): void
    {
        $res = $this->postJson('/api/v1/ProductionBatch/new', [
            'data' => [
                'values' => [
                    'productId' => $this->product->id,
                    'quantityProduced' => 12,
                    'productionDate' => now()->toDateTimeString(),
                ],
            ],
        ], ['Idempotency-Key' => 'produce-toast-'.uniqid()]);

        $res->assertStatus(201);
        $message = $res->json('message') ?? '';
        $this->assertStringContainsString('Main Warehouse', $message);
        $this->assertStringContainsString('Transfer this production', $message);
        $this->assertStringContainsString('Veg Puff', $message);
    }

    public function test_disposed_batch_remains_visible_on_list(): void
    {
        $batch = $this->insertBatch('VP-SEE', 8, now()->subHour());
        $this->product->current_stock = 8;
        $this->product->save();
        WarehouseExpiredStockService::forgetCache();

        $this->postJson("/api/v1/ProductionBatch/{$batch->id}/dispose-expired")->assertSuccessful();

        $show = $this->getJson("/api/v1/ProductionBatch/{$batch->id}");
        $show->assertSuccessful();
        $values = $show->json('data.values') ?? $show->json('data') ?? [];
        $this->assertSame('wasted', strtolower((string) ($values['status'] ?? '')));
        $this->assertSame('Disposed', $values['statusLabel'] ?? null);
        $this->assertSame('Disposed', $values['currentLocation'] ?? null);
    }

    public function test_admin_transfer_destination_search_excludes_warehouse_and_type_suffix(): void
    {
        $generic = $this->getJson('/api/v1/search/branchId?value=');
        $generic->assertSuccessful();
        $genericValues = collect($generic->json('data.results.Branch.values') ?? []);
        $genericIds = $genericValues->pluck('id')->map(fn ($id) => (string) $id);
        $genericLabels = $genericValues->pluck('label');
        $this->assertTrue(
            $genericIds->contains((string) $this->warehouse->id),
            'Generic branch picker should still list warehouse for User assignment. Payload: '.json_encode($generic->json())
        );
        $this->assertTrue($genericLabels->contains('Main Warehouse (Warehouse)'));
        $this->assertTrue($genericLabels->contains('Branch A (Retail)'));

        $retail = $this->getJson('/api/v1/search/branchId?value=&retailOnly=1');
        $retail->assertSuccessful();
        $values = collect($retail->json('data.results.Branch.values') ?? []);
        $ids = $values->pluck('id')->map(fn ($id) => (string) $id);
        $labels = $values->pluck('label');

        $this->assertFalse($ids->contains((string) $this->warehouse->id));
        $this->assertTrue($ids->contains((string) $this->branchA->id));
        $this->assertTrue($labels->contains('Branch A'));
        $this->assertFalse($labels->contains(fn ($label) => str_contains((string) $label, '(Warehouse)')));
        $this->assertFalse($labels->contains(fn ($label) => str_contains((string) $label, '(Retail)')));
    }

    public function test_branch_transfer_field_search_excludes_warehouse(): void
    {
        $fieldId = (string) Str::uuid();
        DB::table('crm_fields')->insert([
            'id' => $fieldId,
            'modulename' => 'BranchTransfer',
            'fieldname' => 'branch_id',
            'fieldlabel' => 'To Branch',
            'fieldtype' => 'relationPickList',
            'tablename' => 'branch_transfers',
            'mandatory' => 1,
            'apifieldname' => 'branchId',
            'displaytype' => 1,
            'is_custom_field' => 0,
            'deleted' => 0,
            'seq' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $res = $this->getJson('/api/v1/search/'.$fieldId.'?value=');
        $res->assertSuccessful();
        $values = collect($res->json('data.results.Branch.values') ?? []);
        $ids = $values->pluck('id')->map(fn ($id) => (string) $id);
        $labels = $values->pluck('label');

        $this->assertFalse($ids->contains((string) $this->warehouse->id));
        $this->assertTrue($ids->contains((string) $this->branchA->id));
        $this->assertTrue($labels->contains('Branch A'));
        $this->assertFalse($labels->contains('Main Warehouse'));
        $this->assertFalse($labels->contains(fn ($label) => str_contains((string) $label, '(Retail)')));
    }

    public function test_cancelled_and_disposed_batches_do_not_count_in_warehouse_current_stock(): void
    {
        $this->insertBatch('VP-OLD-WASTED', 70, now()->subDay(), 'wasted');
        $this->insertBatch('VP-OLD-CANCEL', 10, now()->subDay(), 'cancelled');
        $this->insertBatch('VP-LIVE', 50, now()->addHours(8), 'completed');
        $this->product->current_stock = 120;
        $this->product->save();
        WarehouseExpiredStockService::forgetCache();

        $onHand = WarehouseExpiredStockService::warehouseOnHand(
            (string) $this->org->id,
            (string) $this->product->id,
            true
        );
        $this->assertEquals(50.0, $onHand);
        $this->assertEquals(50.0, (float) $this->product->fresh()->current_stock);

        $blocked = $this->postTransfer((string) $this->branchA->id, 51, 'ghost-stock-block-'.uniqid());
        $blocked->assertStatus(400);
        $this->assertStringContainsString('Available: 50', $blocked->json('message') ?? '');

        $ok = $this->postTransfer((string) $this->branchA->id, 40, 'ghost-stock-ok-'.uniqid());
        $ok->assertStatus(201);
    }
}
