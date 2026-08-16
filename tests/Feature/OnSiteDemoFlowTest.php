<?php

namespace Tests\Feature;

use App\Modules\Api\V1\Billing\Models\Billing;
use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use App\Modules\Api\V1\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Automated pre-flight for the on-site client demo plan (Phases A–C via API).
 * Run: php artisan test --filter=OnSiteDemoFlowTest
 */
class OnSiteDemoFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_on_site_demo_flow_via_api(): void
    {
        // --- A1: Organization registration (same path as /register UI) ---
        $reg = $this->postJson('/api/v1/Organization/new', [
            'data' => [
                'values' => [
                    'name' => 'Client Demo Bakery',
                    'description' => 'On-site demo org',
                    'firstUser' => [
                        'firstName' => 'Demo',
                        'lastName' => 'Admin',
                        'email' => 'demo.admin@client-bakery.test',
                        'phoneNumber' => '+919999999999',
                        'password' => 'Demo@12345',
                        'confirmPassword' => 'Demo@12345',
                    ],
                ],
            ],
        ]);

        $reg->assertStatus(201);
        $orgId = $reg->json('data.org_id');
        $branches = $reg->json('data.branches') ?? [];
        $this->assertNotEmpty($orgId);
        $this->assertNotEmpty($branches);

        $warehouseBranch = collect($branches)->first(fn ($b) => ($b['type'] ?? '') === 'warehouse')
            ?? $branches[0];
        $warehouseBranchId = $warehouseBranch['id'];

        $admin = User::where('email', 'demo.admin@client-bakery.test')->first();
        $this->assertNotNull($admin);
        Sanctum::actingAs($admin);

        // --- A3: Retail sub-branch ---
        $retailRes = $this->postJson('/api/v1/Branch/new', [
            'data' => [
                'values' => [
                    'name' => 'Shop 1',
                    'type' => 'retail',
                    'address' => 'Demo retail branch',
                ],
            ],
        ]);
        $retailRes->assertStatus(201);
        $retailBranchId = $retailRes->json('data.id');
        $this->assertNotEmpty($retailBranchId);

        // --- A4: Staff users (roles seeded on org register) ---
        $salesRoleId = DB::table('roles')->where('organization_id', $orgId)->where('name', 'Sales')->value('id');
        $warehouseRoleId = DB::table('roles')->where('organization_id', $orgId)->where('name', 'Warehouse')->value('id');
        $this->assertNotNull($salesRoleId);
        $this->assertNotNull($warehouseRoleId);

        $this->postJson('/api/v1/settings/User/new', [
            'data' => [
                'values' => [
                    'firstName' => 'Sales',
                    'lastName' => 'Staff',
                    'email' => 'sales.staff@client-bakery.test',
                    'phone' => '+919999999991',
                    'role' => 'staff',
                    'roleId' => $salesRoleId,
                    'branchId' => $retailBranchId,
                    'password' => 'Demo@12345',
                    'confirmPassword' => 'Demo@12345',
                ],
            ],
        ])->assertStatus(201);

        $this->postJson('/api/v1/settings/User/new', [
            'data' => [
                'values' => [
                    'firstName' => 'Warehouse',
                    'lastName' => 'Staff',
                    'email' => 'warehouse.staff@client-bakery.test',
                    'phone' => '+919999999992',
                    'role' => 'staff',
                    'roleId' => $warehouseRoleId,
                    'branchId' => $warehouseBranchId,
                    'password' => 'Demo@12345',
                    'confirmPassword' => 'Demo@12345',
                ],
            ],
        ])->assertStatus(201);

        // --- B1–B3: Vendor, ingredient, stock in ---
        $vendorId = $this->postJson('/api/v1/Vendor/new', [
            'data' => ['values' => ['name' => 'Demo Supplier']],
        ])->assertStatus(201)->json('data.id');

        $ingredientId = $this->postJson('/api/v1/Ingredient/new', [
            'data' => [
                'values' => [
                    'vendorId' => $vendorId,
                    'name' => 'Sugar',
                    'unit' => 'gm',
                ],
            ],
        ])->assertStatus(201)->json('data.id');

        $this->postJson('/api/v1/InventoryTransaction/new', [
            'data' => [
                'values' => [
                    'ingredientId' => $ingredientId,
                    'type' => 'in',
                    'quantity' => 5000,
                    'referenceNote' => 'Demo purchase',
                ],
            ],
        ])->assertStatus(201);

        $this->assertDatabaseHas('ingredients', [
            'id' => $ingredientId,
            'current_stock' => 5000,
        ]);

        // --- B4–B5: Products (pcs + weight) and recipe ---
        $breadId = $this->postJson('/api/v1/Product/new', [
            'data' => [
                'values' => [
                    'name' => 'Demo Bread',
                    'price' => 40,
                    'unit' => 'pcs',
                    'category' => 'bread',
                ],
            ],
        ])->assertStatus(201)->json('data.id');

        $ladduId = $this->postJson('/api/v1/Product/new', [
            'data' => [
                'values' => [
                    'name' => 'Demo Laddu',
                    'price' => 400,
                    'unit' => 'gm',
                    'category' => 'sweet',
                ],
            ],
        ])->assertStatus(201)->json('data.id');

        $this->postJson("/api/v1/Product/{$breadId}/recipe/new", [
            'data' => [
                'values' => [
                    'ingredientId' => $ingredientId,
                    'quantityRequired' => 100,
                ],
            ],
        ])->assertStatus(201);

        // --- B6: Production batch ---
        $this->postJson('/api/v1/ProductionBatch/new', [
            'data' => [
                'values' => [
                    'productId' => $breadId,
                    'quantityProduced' => 20,
                    'productionDate' => now()->toDateString(),
                ],
            ],
        ])->assertSuccessful();

        $this->assertEquals(20.0, (float) DB::table('products')->where('id', $breadId)->value('current_stock'));

        // --- B7–B8: Transfer to retail branch ---
        $this->postJson('/api/v1/BranchTransfer/new', [
            'data' => [
                'values' => [
                    'branchId' => $retailBranchId,
                    'transferDate' => now()->toDateString(),
                    'notes' => 'Demo transfer',
                ],
                'relatedRecords' => [
                    'items' => [
                        ['productId' => $breadId, 'quantity' => 15, 'unit' => 'pcs', 'pieces' => 15],
                    ],
                ],
            ],
        ])->assertStatus(201);

        $retailStock = BranchStock::where('branch_id', $retailBranchId)
            ->where('product_id', $breadId)
            ->first();
        $this->assertNotNull($retailStock);
        $this->assertEquals(15.0, (float) $retailStock->current_stock);

        // Seed weight product on retail for POS (transfer laddu too)
        DB::table('products')->where('id', $ladduId)->update(['current_stock' => 5000]);
        $this->postJson('/api/v1/BranchTransfer/new', [
            'data' => [
                'values' => [
                    'branchId' => $retailBranchId,
                    'transferDate' => now()->toDateString(),
                ],
                'relatedRecords' => [
                    'items' => [
                        ['productId' => $ladduId, 'quantity' => 2000, 'unit' => 'gm', 'pieces' => 8],
                    ],
                ],
            ],
        ])->assertStatus(201);

        // --- C3: Pending bill — no stock deduct ---
        $pending = $this->postJson('/api/v1/Billing/new', [
            'data' => [
                'values' => [
                    'branchId' => $retailBranchId,
                    'paymentMethod' => 'cash',
                    'paymentStatus' => 'pending',
                    'discountAmount' => 0,
                    'taxAmount' => 0,
                ],
                'relatedRecords' => [
                    'items' => [
                        [
                            'productId' => $breadId,
                            'quantity' => 2,
                            'unit' => 'pcs',
                            'category' => 'bread',
                        ],
                    ],
                ],
            ],
        ]);
        $pending->assertSuccessful();
        $this->assertEquals(15.0, (float) $retailStock->fresh()->current_stock);

        // --- C4: Paid bill (pcs + weight) ---
        $paid = $this->postJson('/api/v1/Billing/new', [
            'data' => [
                'values' => [
                    'branchId' => $retailBranchId,
                    'paymentMethod' => 'cash',
                    'paymentStatus' => 'paid',
                    'discountAmount' => 0,
                    'taxAmount' => 0,
                ],
                'relatedRecords' => [
                    'items' => [
                        [
                            'productId' => $breadId,
                            'quantity' => 3,
                            'unit' => 'pcs',
                            'category' => 'bread',
                        ],
                        [
                            'productId' => $ladduId,
                            'quantity' => 250,
                            'unit' => 'gm',
                            'category' => 'sweet',
                        ],
                    ],
                ],
            ],
        ], ['Idempotency-Key' => 'onsitedemoflowtest-paid-2']);
        $paid->assertSuccessful();
        $billId = $paid->json('data.id') ?? $paid->json('data.values.id');
        $this->assertNotEmpty($billId);
        $this->assertEquals(12.0, (float) $retailStock->fresh()->current_stock);

        // --- C5: Cancel restores stock ---
        $this->postJson("/api/v1/Billing/{$billId}", [
            'data' => ['values' => ['paymentStatus' => 'cancelled']],
        ])->assertSuccessful();
        $this->assertEquals(15.0, (float) $retailStock->fresh()->current_stock);
        $this->assertEquals('Cancelled', Billing::find($billId)->payment_status);

        // --- C8: Dashboard summary loads ---
        $this->getJson('/api/v1/Dashboard/Summary')->assertSuccessful();
    }
}
