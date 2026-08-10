<?php

namespace Tests\Feature;

use App\Modules\Api\V1\Billing\Models\Billing;
use App\Modules\Api\V1\Branch\Models\Branch;
use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $adminA;
    private User $staffA;
    private Branch $branchA;
    private Branch $branchB;
    private Product $productA;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('login:ip:127.0.0.1');

        $this->orgA = Organization::create(['name' => 'Org A Bakery', 'email' => 'orga@example.com']);
        $this->orgB = Organization::create(['name' => 'Org B Bakery', 'email' => 'orgb@example.com']);

        $this->branchA = new Branch();
        $this->branchA->organization_id = $this->orgA->id;
        $this->branchA->name = 'Branch A';
        $this->branchA->save();

        $this->branchB = new Branch();
        $this->branchB->organization_id = $this->orgA->id;
        $this->branchB->name = 'Branch B';
        $this->branchB->save();

        $this->adminA = User::create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branchA->id,
            'first_name' => 'Admin',
            'last_name' => 'A',
            'email' => 'admin-a@example.com',
            'role' => 'admin',
            'password' => Hash::make('Password1'),
        ]);

        $this->staffA = User::create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branchA->id,
            'first_name' => 'Staff',
            'last_name' => 'A',
            'email' => 'staff-a@example.com',
            'role' => 'staff',
            'password' => Hash::make('Password1'),
        ]);

        $this->productA = new Product();
        $this->productA->organization_id = $this->orgA->id;
        $this->productA->name = 'Bun';
        $this->productA->price = 25;
        $this->productA->unit = 'pcs';
        $this->productA->category = 'bakery';
        $this->productA->current_stock = 0;
        $this->productA->save();
    }

    public function test_cannot_search_other_organizations(): void
    {
        Sanctum::actingAs($this->staffA);

        $response = $this->getJson('/api/v1/Organization/search?query=Bakery');

        $response->assertSuccessful();
        $list = $response->json('data.list') ?? [];
        foreach ($list as $row) {
            $id = $row['id'] ?? $row['values']['id'] ?? null;
            if ($id) {
                $this->assertSame($this->orgA->id, $id);
            }
        }
    }

    public function test_cannot_delete_other_organization(): void
    {
        Sanctum::actingAs($this->adminA);

        $response = $this->deleteJson('/api/v1/Organization/' . $this->orgB->id);

        $response->assertStatus(403);
        $this->assertDatabaseHas('organizations', ['id' => $this->orgB->id]);
    }

    public function test_staff_cannot_access_dashboard(): void
    {
        Sanctum::actingAs($this->staffA);

        $this->getJson('/api/v1/Dashboard/Summary')->assertStatus(403);
    }

    public function test_staff_cannot_view_other_branch_stock(): void
    {
        Sanctum::actingAs($this->staffA);

        BranchStock::create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branchB->id,
            'product_id' => $this->productA->id,
            'current_stock' => 99,
        ]);

        BranchStock::create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branchA->id,
            'product_id' => $this->productA->id,
            'current_stock' => 5,
        ]);

        $response = $this->getJson('/api/v1/BranchStock?branchId=' . $this->branchB->id);
        $this->assertTrue(in_array($response->status(), [200, 403], true));

        if ($response->status() === 200) {
            $list = $response->json('data.list') ?? [];
            foreach ($list as $row) {
                $branchId = $row['branchId'] ?? $row['branch_id'] ?? null;
                if ($branchId) {
                    $this->assertSame($this->branchA->id, $branchId);
                }
            }
        }
    }

    public function test_billing_uses_catalog_price_not_client_price(): void
    {
        Sanctum::actingAs($this->adminA);

        BranchStock::create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branchA->id,
            'product_id' => $this->productA->id,
            'current_stock' => 10,
        ]);

        $response = $this->postJson('/api/v1/Billing/new', [
            'data' => [
                'values' => [
                    'branchId' => $this->branchA->id,
                    'paymentMethod' => 'cash',
                    'paymentStatus' => 'paid',
                    'discountAmount' => 0,
                    'taxAmount' => 0,
                ],
                'relatedRecords' => [
                    'items' => [
                        [
                            'productId' => $this->productA->id,
                            'quantity' => 2,
                            'unitPrice' => 1,
                            'unit' => 'pcs',
                            'category' => 'bakery',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertSuccessful();
        $payload = $response->json('data');
        $billId = data_get($payload, 'id') ?? data_get($payload, 'values.id');
        $this->assertNotEmpty($billId, 'Unexpected payload: ' . json_encode($payload));

        $billing = Billing::with('items')->find($billId);
        $this->assertNotNull($billing);
        $this->assertEquals(50.0, (float) $billing->grand_total);
        $this->assertEquals(25.0, (float) $billing->items->first()->unit_price);
    }

    public function test_weight_billing_uses_grams_and_price_per_kg(): void
    {
        Sanctum::actingAs($this->adminA);

        $laddu = new Product();
        $laddu->organization_id = $this->orgA->id;
        $laddu->name = 'Laddu';
        $laddu->price = 400;
        $laddu->unit = 'gm';
        $laddu->category = 'sweet';
        $laddu->current_stock = 0;
        $laddu->save();

        BranchStock::create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branchA->id,
            'product_id' => $laddu->id,
            'current_stock' => 5000,
        ]);

        $response = $this->postJson('/api/v1/Billing/new', [
            'data' => [
                'values' => [
                    'branchId' => $this->branchA->id,
                    'paymentMethod' => 'cash',
                    'paymentStatus' => 'paid',
                    'discountAmount' => 0,
                    'taxAmount' => 0,
                ],
                'relatedRecords' => [
                    'items' => [
                        [
                            'productId' => $laddu->id,
                            'quantity' => 250,
                            'unitPrice' => 1,
                            'unit' => 'gm',
                            'category' => 'sweet',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertSuccessful();
        $payload = $response->json('data');
        $billId = data_get($payload, 'id') ?? data_get($payload, 'values.id');
        $billing = Billing::with('items')->find($billId);

        $this->assertNotNull($billing);
        $this->assertEquals(100.0, (float) $billing->grand_total);
        $this->assertEquals(100.0, (float) $billing->items->first()->total_price);
        $this->assertEquals(400.0, (float) $billing->items->first()->unit_price);
    }

    public function test_weight_billing_large_quantity_matches_frontend_formula(): void
    {
        Sanctum::actingAs($this->adminA);

        $laddu = new Product();
        $laddu->organization_id = $this->orgA->id;
        $laddu->name = 'Bulk Laddu';
        $laddu->price = 400;
        $laddu->unit = 'gm';
        $laddu->category = 'sweet';
        $laddu->current_stock = 0;
        $laddu->save();

        BranchStock::create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branchA->id,
            'product_id' => $laddu->id,
            'current_stock' => 10000,
        ]);

        $response = $this->postJson('/api/v1/Billing/new', [
            'data' => [
                'values' => [
                    'branchId' => $this->branchA->id,
                    'paymentMethod' => 'cash',
                    'paymentStatus' => 'paid',
                    'discountAmount' => 0,
                    'taxAmount' => 0,
                ],
                'relatedRecords' => [
                    'items' => [
                        [
                            'productId' => $laddu->id,
                            'quantity' => 1500,
                            'unit' => 'gm',
                            'category' => 'sweet',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertSuccessful();
        $payload = $response->json('data');
        $billId = data_get($payload, 'id') ?? data_get($payload, 'values.id');
        $billing = Billing::find($billId);

        $this->assertEquals(600.0, (float) $billing->grand_total);
    }

    public function test_discount_cannot_exceed_subtotal(): void
    {
        Sanctum::actingAs($this->adminA);

        BranchStock::create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branchA->id,
            'product_id' => $this->productA->id,
            'current_stock' => 10,
        ]);

        $response = $this->postJson('/api/v1/Billing/new', [
            'data' => [
                'values' => [
                    'branchId' => $this->branchA->id,
                    'paymentMethod' => 'cash',
                    'paymentStatus' => 'paid',
                    'discountAmount' => 9999,
                    'taxAmount' => 0,
                ],
                'relatedRecords' => [
                    'items' => [
                        [
                            'productId' => $this->productA->id,
                            'quantity' => 1,
                            'unitPrice' => 25,
                            'unit' => 'pcs',
                            'category' => 'bakery',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('discount', strtolower($response->json('message') ?? ''));
    }

    public function test_inline_edit_blocks_payment_status(): void
    {
        Sanctum::actingAs($this->adminA);

        $billing = new Billing();
        $billing->organization_id = $this->orgA->id;
        $billing->branch_id = $this->branchA->id;
        $billing->bill_number = 'BILL-TEST-1';
        $billing->sub_total = 10;
        $billing->discount_amount = 0;
        $billing->tax_amount = 0;
        $billing->grand_total = 10;
        $billing->payment_method = 'Cash';
        $billing->payment_status = 'Pending';
        $billing->billing_date = now();
        $billing->save();

        $response = $this->patchJson('/api/v1/Billing/' . $billing->id . '/inline-edit', [
            'field' => 'paymentStatus',
            'value' => 'Paid',
        ]);

        $response->assertStatus(403);
        $this->assertEquals('Pending', $billing->fresh()->payment_status);
    }

    public function test_pagination_is_capped(): void
    {
        Sanctum::actingAs($this->adminA);

        $response = $this->getJson('/api/v1/Product?per_page=100000');

        $response->assertSuccessful();
        $perPage = (int) ($response->json('data.meta.per_page') ?? 0);
        $this->assertLessThanOrEqual(100, $perPage);
        $this->assertGreaterThan(0, $perPage);
    }

    public function test_x_org_id_mismatch_is_rejected(): void
    {
        Sanctum::actingAs($this->staffA);

        $response = $this->withHeader('X-Org-Id', $this->orgB->id)
            ->getJson('/api/v1/Product');

        $response->assertStatus(403);
    }

    public function test_staff_cannot_edit_product_via_api_without_permission(): void
    {
        Sanctum::actingAs($this->staffA);

        $response = $this->postJson('/api/v1/Product/' . $this->productA->id, [
            'data' => [
                'values' => [
                    'name' => 'Hacked',
                    'price' => 1,
                    'unit' => 'pcs',
                    'category' => 'bakery',
                ],
            ],
        ]);

        $this->assertNotEquals(200, $response->status(), 'Staff must not update products: ' . $response->getContent());
        $this->assertEquals('Bun', $this->productA->fresh()->name);
        $this->assertEquals(25.0, (float) $this->productA->fresh()->price);
    }
}
