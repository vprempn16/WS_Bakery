<?php

namespace Tests\Feature;

use App\Modules\Api\V1\Branch\Models\Branch;
use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use App\Modules\Api\V1\BranchTransfer\Models\BranchTransfer;
use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BranchTransferLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private Branch $warehouse;

    private Branch $retail;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->org = Organization::create(['name' => 'Lifecycle Bakery']);
        $this->admin = User::create([
            'organization_id' => $this->org->id,
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin-lifecycle@example.com',
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);

        $this->warehouse = new Branch();
        $this->warehouse->organization_id = $this->org->id;
        $this->warehouse->name = 'Warehouse';
        $this->warehouse->type = 'warehouse';
        $this->warehouse->save();

        $this->retail = new Branch();
        $this->retail->organization_id = $this->org->id;
        $this->retail->name = 'Retail';
        $this->retail->type = 'retail';
        $this->retail->save();

        $this->admin->branch_id = $this->warehouse->id;
        $this->admin->save();

        $this->product = new Product();
        $this->product->organization_id = $this->org->id;
        $this->product->name = 'Bread';
        $this->product->price = 40;
        $this->product->unit = 'pcs';
        $this->product->category = 'bread';
        $this->product->status = 'active';
        $this->product->current_stock = 100;
        $this->product->save();
    }

    private function createPendingTransfer(float $qty = 10): string
    {
        $res = $this->postJson('/api/v1/BranchTransfer/new', [
            'data' => [
                'values' => [
                    'branchId' => $this->retail->id,
                    'transferDate' => now()->toDateString(),
                    'notes' => 'Lifecycle test',
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
        ], ['Idempotency-Key' => 'lifecycle-' . uniqid('', true)]);

        $res->assertStatus(201);

        return (string) ($res->json('data.id') ?? $res->json('data.values.id'));
    }

    private function transition(string $transferId, string $status)
    {
        return $this->postJson("/api/v1/BranchTransfer/{$transferId}", [
            'data' => ['values' => ['status' => $status]],
        ]);
    }

    public function test_create_is_pending_and_does_not_move_stock(): void
    {
        Sanctum::actingAs($this->admin);

        $id = $this->createPendingTransfer(10);

        $transfer = BranchTransfer::findOrFail($id);
        $this->assertEquals('pending', $transfer->status);
        $this->assertEquals(100.0, (float) $this->product->fresh()->current_stock);
        $this->assertNull(
            BranchStock::where('branch_id', $this->retail->id)
                ->where('product_id', $this->product->id)
                ->first()
        );
    }

    public function test_pending_item_sync_does_not_move_stock_and_dispatch_uses_new_qty(): void
    {
        Sanctum::actingAs($this->admin);

        $id = $this->createPendingTransfer(10);
        $this->assertEquals(100.0, (float) $this->product->fresh()->current_stock);

        $this->postJson("/api/v1/BranchTransfer/{$id}", [
            'data' => [
                'relatedRecords' => [
                    'items' => [
                        [
                            'productId' => $this->product->id,
                            'quantity' => 4,
                            'unit' => 'pcs',
                            'pieces' => 4,
                        ],
                    ],
                ],
            ],
        ])->assertSuccessful();

        $transfer = BranchTransfer::with('items')->findOrFail($id);
        $this->assertEquals('pending', $transfer->status);
        $this->assertCount(1, $transfer->items);
        $this->assertEquals(4.0, (float) $transfer->items->first()->quantity);
        $this->assertEquals(100.0, (float) $this->product->fresh()->current_stock);

        $this->transition($id, 'dispatched')->assertSuccessful();
        $this->assertEquals(96.0, (float) $this->product->fresh()->current_stock);
        $this->assertNull(
            BranchStock::where('branch_id', $this->retail->id)
                ->where('product_id', $this->product->id)
                ->first()
        );
    }

    public function test_cannot_edit_items_after_dispatch(): void
    {
        Sanctum::actingAs($this->admin);

        $id = $this->createPendingTransfer(10);
        $this->transition($id, 'dispatched')->assertSuccessful();

        $this->postJson("/api/v1/BranchTransfer/{$id}", [
            'data' => [
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
        ])->assertStatus(400);

        $this->assertEquals(1, BranchTransfer::findOrFail($id)->items()->count());
        $this->assertEquals(10.0, (float) BranchTransfer::findOrFail($id)->items()->first()->quantity);
    }

    public function test_dispatch_deducts_warehouse_only(): void
    {
        Sanctum::actingAs($this->admin);

        $id = $this->createPendingTransfer(10);
        $this->transition($id, 'dispatched')->assertSuccessful();

        $this->assertEquals('dispatched', BranchTransfer::findOrFail($id)->status);
        $this->assertEquals(90.0, (float) $this->product->fresh()->current_stock);
        $this->assertNull(
            BranchStock::where('branch_id', $this->retail->id)
                ->where('product_id', $this->product->id)
                ->first()
        );
    }

    public function test_receive_credits_branch_stock(): void
    {
        Sanctum::actingAs($this->admin);

        $id = $this->createPendingTransfer(10);
        $this->transition($id, 'dispatched')->assertSuccessful();
        $this->transition($id, 'received')->assertSuccessful();

        $this->assertEquals('received', BranchTransfer::findOrFail($id)->status);
        $this->assertEquals(90.0, (float) $this->product->fresh()->current_stock);
        $this->assertEquals(
            10.0,
            (float) BranchStock::where('branch_id', $this->retail->id)
                ->where('product_id', $this->product->id)
                ->value('current_stock')
        );
    }

    public function test_cancel_pending_is_noop_for_stock(): void
    {
        Sanctum::actingAs($this->admin);

        $id = $this->createPendingTransfer(10);
        $this->deleteJson("/api/v1/BranchTransfer/{$id}")->assertSuccessful();

        $this->assertEquals('cancelled', BranchTransfer::findOrFail($id)->status);
        $this->assertEquals(100.0, (float) $this->product->fresh()->current_stock);
    }

    public function test_cancel_dispatched_restores_warehouse(): void
    {
        Sanctum::actingAs($this->admin);

        $id = $this->createPendingTransfer(10);
        $this->transition($id, 'dispatched')->assertSuccessful();
        $this->transition($id, 'cancelled')->assertSuccessful();

        $this->assertEquals('cancelled', BranchTransfer::findOrFail($id)->status);
        $this->assertEquals(100.0, (float) $this->product->fresh()->current_stock);
    }

    public function test_cancel_received_reverses_both_sides(): void
    {
        Sanctum::actingAs($this->admin);

        $id = $this->createPendingTransfer(10);
        $this->transition($id, 'dispatched')->assertSuccessful();
        $this->transition($id, 'received')->assertSuccessful();
        $this->transition($id, 'cancelled')->assertSuccessful();

        $this->assertEquals('cancelled', BranchTransfer::findOrFail($id)->status);
        $this->assertEquals(100.0, (float) $this->product->fresh()->current_stock);
        $this->assertEquals(
            0.0,
            (float) BranchStock::where('branch_id', $this->retail->id)
                ->where('product_id', $this->product->id)
                ->value('current_stock')
        );
    }

    public function test_cannot_receive_before_dispatch(): void
    {
        Sanctum::actingAs($this->admin);

        $id = $this->createPendingTransfer(10);
        $this->transition($id, 'received')->assertStatus(400);
        $this->assertEquals(100.0, (float) $this->product->fresh()->current_stock);
    }

    public function test_dispatch_rejects_insufficient_warehouse_stock(): void
    {
        Sanctum::actingAs($this->admin);

        $id = $this->createPendingTransfer(10);
        $this->product->current_stock = 2;
        $this->product->save();

        $this->transition($id, 'dispatched')->assertStatus(400);
        $this->assertEquals('pending', BranchTransfer::findOrFail($id)->status);
        $this->assertEquals(2.0, (float) $this->product->fresh()->current_stock);
    }

    public function test_legacy_completed_cancel_still_reverses_stock(): void
    {
        Sanctum::actingAs($this->admin);

        $transfer = new BranchTransfer();
        $transfer->organization_id = $this->org->id;
        $transfer->branch_id = $this->retail->id;
        $transfer->transfer_date = now()->toDateString();
        $transfer->status = 'completed';
        $transfer->created_by = $this->admin->id;
        $transfer->notes = 'legacy';
        $transfer->save();

        $transfer->items()->create([
            'organization_id' => $this->org->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit' => 'pcs',
            'pieces' => 5,
        ]);

        $this->product->current_stock = 95;
        $this->product->save();

        BranchStock::create([
            'organization_id' => $this->org->id,
            'branch_id' => $this->retail->id,
            'product_id' => $this->product->id,
            'current_stock' => 5,
        ]);

        $this->transition($transfer->id, 'cancelled')->assertSuccessful();
        $this->assertEquals(100.0, (float) $this->product->fresh()->current_stock);
        $this->assertEquals(
            0.0,
            (float) BranchStock::where('branch_id', $this->retail->id)
                ->where('product_id', $this->product->id)
                ->value('current_stock')
        );
    }

    public function test_gm_product_can_store_optional_pieces(): void
    {
        Sanctum::actingAs($this->admin);

        $laddu = new Product();
        $laddu->organization_id = $this->org->id;
        $laddu->name = 'Laddu';
        $laddu->price = 400;
        $laddu->unit = 'gm';
        $laddu->category = 'sweet';
        $laddu->status = 'active';
        $laddu->current_stock = 5000;
        $laddu->save();

        $res = $this->postJson('/api/v1/BranchTransfer/new', [
            'data' => [
                'values' => [
                    'branchId' => $this->retail->id,
                    'transferDate' => now()->toDateString(),
                ],
                'relatedRecords' => [
                    'items' => [
                        [
                            'productId' => $laddu->id,
                            'quantity' => 1667,
                            'unit' => 'gm',
                            'pieces' => 50,
                        ],
                    ],
                ],
            ],
        ], ['Idempotency-Key' => 'lifecycle-gm-pieces-' . uniqid('', true)]);

        $res->assertStatus(201);
        $transferId = (string) ($res->json('data.id') ?? $res->json('data.values.id'));
        $item = BranchTransfer::findOrFail($transferId)->items()->first();
        $this->assertEquals(50.0, (float) $item->pieces);
        $this->assertEquals(1667.0, (float) $item->quantity);
    }

    public function test_gm_product_succeeds_without_pieces(): void
    {
        Sanctum::actingAs($this->admin);

        $laddu = new Product();
        $laddu->organization_id = $this->org->id;
        $laddu->name = 'Bulk Sweet';
        $laddu->price = 400;
        $laddu->unit = 'gm';
        $laddu->category = 'sweet';
        $laddu->status = 'active';
        $laddu->current_stock = 5000;
        $laddu->save();

        $res = $this->postJson('/api/v1/BranchTransfer/new', [
            'data' => [
                'values' => [
                    'branchId' => $this->retail->id,
                    'transferDate' => now()->toDateString(),
                ],
                'relatedRecords' => [
                    'items' => [
                        [
                            'productId' => $laddu->id,
                            'quantity' => 1000,
                            'unit' => 'gm',
                        ],
                    ],
                ],
            ],
        ], ['Idempotency-Key' => 'lifecycle-gm-no-pieces-' . uniqid('', true)]);

        $res->assertStatus(201);
        $transferId = (string) ($res->json('data.id') ?? $res->json('data.values.id'));
        $item = BranchTransfer::findOrFail($transferId)->items()->first();
        $this->assertNull($item->pieces);
    }

    public function test_pcs_product_requires_pieces(): void
    {
        Sanctum::actingAs($this->admin);

        $res = $this->postJson('/api/v1/BranchTransfer/new', [
            'data' => [
                'values' => [
                    'branchId' => $this->retail->id,
                    'transferDate' => now()->toDateString(),
                ],
                'relatedRecords' => [
                    'items' => [
                        [
                            'productId' => $this->product->id,
                            'quantity' => 5,
                            'unit' => 'pcs',
                        ],
                    ],
                ],
            ],
        ], ['Idempotency-Key' => 'lifecycle-pcs-no-pieces-' . uniqid('', true)]);

        $res->assertStatus(422);
    }
}
