<?php

namespace Tests\Feature;

use App\Modules\Api\V1\Billing\Models\Billing;
use App\Modules\Api\V1\Branch\Models\Branch;
use App\Modules\Api\V1\BranchSales\Models\BranchDailyReport;
use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private User $staff;
    private Branch $branchA;
    private Branch $branchB;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['name' => 'Auth Bakery']);
        $this->branchA = new Branch();
        $this->branchA->organization_id = $this->org->id;
        $this->branchA->name = 'Branch A';
        $this->branchA->save();

        $this->branchB = new Branch();
        $this->branchB->organization_id = $this->org->id;
        $this->branchB->name = 'Branch B';
        $this->branchB->save();

        $this->admin = User::create([
            'organization_id' => $this->org->id,
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin-auth@example.com',
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);

        $this->staff = User::create([
            'organization_id' => $this->org->id,
            'branch_id' => $this->branchA->id,
            'first_name' => 'Cashier',
            'last_name' => 'Staff',
            'email' => 'staff-auth@example.com',
            'role' => 'staff',
            'password' => Hash::make('password'),
        ]);

        $this->product = new Product();
        $this->product->organization_id = $this->org->id;
        $this->product->name = 'Bun';
        $this->product->price = 20;
        $this->product->unit = 'pcs';
        $this->product->category = 'bakery';
        $this->product->current_stock = 0;
        $this->product->save();
    }

    public function test_staff_cannot_list_settings_users(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->getJson('/api/v1/settings/User');

        $response->assertStatus(403);
    }

    public function test_admin_can_list_settings_users(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/settings/User');

        $response->assertSuccessful();
    }

    public function test_staff_cannot_bill_other_branch(): void
    {
        Sanctum::actingAs($this->staff);

        $this->assertFalse($this->staff->isFullAdmin());
        $this->assertEquals($this->branchA->id, $this->staff->fresh()->branch_id);
        $this->assertFalse(\App\Services\BranchAccess::canAccessBranch($this->staff->fresh(), $this->branchB->id));

        BranchStock::create([
            'organization_id' => $this->org->id,
            'branch_id' => $this->branchB->id,
            'product_id' => $this->product->id,
            'current_stock' => 10,
        ]);

        $response = $this->postJson('/api/v1/Billing/new', [
            'data' => [
                'values' => [
                    'branchId' => $this->branchB->id,
                    'paymentMethod' => 'cash',
                    'paymentStatus' => 'paid',
                    'discountAmount' => 0,
                    'taxAmount' => 0,
                ],
                'relatedRecords' => [
                    'items' => [
                        [
                            'productId' => $this->product->id,
                            'quantity' => 1,
                            'unitPrice' => 20,
                            'unit' => 'pcs',
                            'category' => 'bakery',
                        ],
                    ],
                ],
            ],
        ], ['Idempotency-Key' => 'staff-bill-other-branch-1']);

        $response->assertStatus(400);
        $this->assertStringContainsString('branch', strtolower($response->json('message') ?? ''));
        $this->assertDatabaseCount('billings', 0);
    }

    public function test_staff_daily_report_ignores_spoofed_branch_header(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this
            ->withHeader('X-Branch-Id', (string) $this->branchB->id)
            ->postJson('/api/v1/BranchDailyReport/new', [
                'data' => [
                    'values' => [
                        'reportDate' => now()->toDateString(),
                    ],
                ],
            ]);

        $response->assertCreated();
        $report = BranchDailyReport::firstOrFail();
        $this->assertSame((string) $this->branchA->id, (string) $report->branch_id);
        $this->assertNotSame((string) $this->branchB->id, (string) $report->branch_id);
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'data' => [
                    'values' => [
                        'email' => 'nobody@example.com',
                        'password' => 'wrong',
                    ],
                ],
            ]);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'data' => [
                'values' => [
                    'email' => 'nobody@example.com',
                    'password' => 'wrong',
                ],
            ],
        ]);

        $response->assertStatus(429);
    }

    public function test_staff_without_branch_cannot_list_billing(): void
    {
        $this->staff->branch_id = null;
        $this->staff->save();

        Sanctum::actingAs($this->staff->fresh());

        $this->getJson('/api/v1/Billing')->assertStatus(403);
    }

    public function test_drafts_are_scoped_to_branch_header(): void
    {
        Sanctum::actingAs($this->admin);

        $pendingA = new Billing();
        $pendingA->organization_id = $this->org->id;
        $pendingA->branch_id = $this->branchA->id;
        $pendingA->bill_number = 'BILL-DRAFT-A';
        $pendingA->customer_name = 'A Customer';
        $pendingA->payment_status = 'Pending';
        $pendingA->payment_method = 'Cash';
        $pendingA->billing_date = now();
        $pendingA->sub_total = 20;
        $pendingA->grand_total = 20;
        $pendingA->save();

        $pendingB = new Billing();
        $pendingB->organization_id = $this->org->id;
        $pendingB->branch_id = $this->branchB->id;
        $pendingB->bill_number = 'BILL-DRAFT-B';
        $pendingB->customer_name = 'B Customer';
        $pendingB->payment_status = 'Pending';
        $pendingB->payment_method = 'Cash';
        $pendingB->billing_date = now();
        $pendingB->sub_total = 30;
        $pendingB->grand_total = 30;
        $pendingB->save();

        $resA = $this->withHeader('X-Branch-Id', (string) $this->branchA->id)
            ->getJson('/api/v1/Billing/drafts');
        $resA->assertSuccessful();
        $idsA = collect($resA->json('data.list') ?? [])->pluck('id')->all();
        $this->assertContains($pendingA->id, $idsA);
        $this->assertNotContains($pendingB->id, $idsA);

        $resB = $this->withHeader('X-Branch-Id', (string) $this->branchB->id)
            ->getJson('/api/v1/Billing/drafts');
        $resB->assertSuccessful();
        $idsB = collect($resB->json('data.list') ?? [])->pluck('id')->all();
        $this->assertContains($pendingB->id, $idsB);
        $this->assertNotContains($pendingA->id, $idsB);
    }

    public function test_staff_cannot_view_other_branch_transfer(): void
    {
        Sanctum::actingAs($this->admin);

        $transfer = new \App\Modules\Api\V1\BranchTransfer\Models\BranchTransfer();
        $transfer->organization_id = $this->org->id;
        $transfer->branch_id = $this->branchB->id;
        $transfer->transfer_date = now()->toDateString();
        $transfer->status = 'completed';
        $transfer->created_by = $this->admin->id;
        $transfer->save();

        Sanctum::actingAs($this->staff);

        $this->getJson('/api/v1/BranchTransfer/' . $transfer->id)->assertStatus(403);
    }

    public function test_staff_cannot_view_other_branch_transfer_invoice(): void
    {
        Sanctum::actingAs($this->admin);

        $transfer = new \App\Modules\Api\V1\BranchTransfer\Models\BranchTransfer();
        $transfer->organization_id = $this->org->id;
        $transfer->branch_id = $this->branchB->id;
        $transfer->transfer_date = now()->toDateString();
        $transfer->status = 'completed';
        $transfer->created_by = $this->admin->id;
        $transfer->save();

        Sanctum::actingAs($this->staff);

        $this->getJson('/api/v1/BranchTransfer/' . $transfer->id . '/invoice')->assertStatus(403);
    }

    public function test_destination_branch_staff_can_receive_but_cannot_dispatch_transfer(): void
    {
        $this->branchA->type = 'retail';
        $this->branchA->save();

        $transfer = new \App\Modules\Api\V1\BranchTransfer\Models\BranchTransfer();
        $transfer->organization_id = $this->org->id;
        $transfer->branch_id = $this->branchA->id;
        $transfer->transfer_date = now()->toDateString();
        $transfer->status = 'dispatched';
        $transfer->created_by = $this->admin->id;
        $transfer->save();

        $item = new \App\Modules\Api\V1\BranchTransfer\Models\BranchTransferItem();
        $item->organization_id = $this->org->id;
        $item->branch_transfer_id = $transfer->id;
        $item->product_id = $this->product->id;
        $item->quantity = 5;
        $item->unit = 'pcs';
        $item->pieces = 5;
        $item->save();

        Sanctum::actingAs($this->staff);

        $this->postJson('/api/v1/BranchTransfer/' . $transfer->id, [
            'data' => ['values' => ['status' => 'received']],
        ])->assertSuccessful();

        $this->assertEquals('received', $transfer->fresh()->status);
        $this->assertEquals(
            5.0,
            (float) BranchStock::where('branch_id', $this->branchA->id)
                ->where('product_id', $this->product->id)
                ->value('current_stock')
        );

        $pending = new \App\Modules\Api\V1\BranchTransfer\Models\BranchTransfer();
        $pending->organization_id = $this->org->id;
        $pending->branch_id = $this->branchA->id;
        $pending->transfer_date = now()->toDateString();
        $pending->status = 'pending';
        $pending->created_by = $this->admin->id;
        $pending->save();

        $this->postJson('/api/v1/BranchTransfer/' . $pending->id, [
            'data' => ['values' => ['status' => 'dispatched']],
        ])->assertStatus(403);

        // Branch staff may view/receive, but cannot edit pending items without edit permission.
        $this->postJson('/api/v1/BranchTransfer/' . $pending->id, [
            'data' => [
                'relatedRecords' => [
                    'items' => [
                        [
                            'productId' => $this->product->id,
                            'quantity' => 2,
                            'unit' => 'pcs',
                            'pieces' => 2,
                        ],
                    ],
                ],
            ],
        ])->assertStatus(403);
    }

    public function test_warehouse_staff_can_create_transfer_to_retail_branch(): void
    {
        $warehouse = new Branch();
        $warehouse->organization_id = $this->org->id;
        $warehouse->name = 'Central Warehouse';
        $warehouse->type = 'warehouse';
        $warehouse->save();

        $this->branchA->type = 'retail';
        $this->branchA->save();

        $warehouseStaff = User::create([
            'organization_id' => $this->org->id,
            'branch_id' => $warehouse->id,
            'first_name' => 'Warehouse',
            'last_name' => 'Clerk',
            'email' => 'warehouse-auth@example.com',
            'role' => 'warehouse',
            'password' => Hash::make('password'),
        ]);

        $this->product->current_stock = 100;
        $this->product->status = 'active';
        $this->product->save();

        Sanctum::actingAs($warehouseStaff);

        $this->assertTrue(\App\Services\BranchAccess::isWarehouseUser($warehouseStaff->fresh()));
        $this->assertTrue(
            \App\Services\BranchAccess::canAccessTransferDestination($warehouseStaff->fresh(), (string) $this->branchA->id)
        );
        $this->assertFalse(
            \App\Services\BranchAccess::canAccessBranch($warehouseStaff->fresh(), (string) $this->branchA->id)
        );

        $response = $this->withHeader('X-Branch-Id', (string) $warehouse->id)
            ->postJson('/api/v1/BranchTransfer/new', [
                'data' => [
                    'values' => [
                        'branchId' => $this->branchA->id,
                        'transferDate' => now()->toDateString(),
                    ],
                    'relatedRecords' => [
                        'items' => [
                            [
                                'productId' => $this->product->id,
                                'quantity' => 5,
                                'unit' => 'pcs',
                                'pieces' => 5,
                            ],
                        ],
                    ],
                ],
            ], ['Idempotency-Key' => 'warehouse-staff-create-transfer-1']);

        $response->assertStatus(201);

        $transferId = (string) \App\Modules\Api\V1\BranchTransfer\Models\BranchTransfer::query()
            ->where('created_by', $warehouseStaff->id)
            ->latest('created_at')
            ->value('id');

        $this->assertNotSame('', $transferId);
        $this->withHeader('X-Branch-Id', (string) $warehouse->id)
            ->getJson('/api/v1/BranchTransfer/' . $transferId)
            ->assertSuccessful();
        $this->withHeader('X-Branch-Id', (string) $warehouse->id)
            ->getJson('/api/v1/BranchTransfer/' . $transferId . '/audit-log')
            ->assertSuccessful();
        $this->withHeader('X-Branch-Id', (string) $warehouse->id)
            ->getJson('/api/v1/BranchTransfer/' . $transferId . '/invoice')
            ->assertSuccessful();
    }

    public function test_staff_cannot_view_other_branch_inventory(): void
    {
        Sanctum::actingAs($this->staff);

        $this->getJson('/api/v1/Branch/' . $this->branchB->id . '/inventory')->assertStatus(403);
    }

    public function test_gm_transfer_does_not_require_pieces(): void
    {
        Sanctum::actingAs($this->admin);

        $this->branchA->type = 'retail';
        $this->branchA->save();

        $weightProduct = new Product();
        $weightProduct->organization_id = $this->org->id;
        $weightProduct->name = 'Laddu';
        $weightProduct->price = 40;
        $weightProduct->unit = 'gm';
        $weightProduct->category = 'sweet';
        $weightProduct->current_stock = 5000;
        $weightProduct->status = 'active';
        $weightProduct->save();

        $response = $this->postJson('/api/v1/BranchTransfer/new', [
            'data' => [
                'values' => [
                    'branchId' => $this->branchA->id,
                    'transferDate' => now()->toDateString(),
                ],
                'relatedRecords' => [
                    'items' => [
                        ['productId' => $weightProduct->id, 'quantity' => 250, 'unit' => 'gm'],
                    ],
                ],
            ],
        ], ['Idempotency-Key' => 'auth-hardening-gm-no-pieces']);

        $response->assertStatus(201);
    }

    public function test_pcs_transfer_requires_pieces(): void
    {
        Sanctum::actingAs($this->admin);

        $this->branchA->type = 'retail';
        $this->branchA->save();
        $this->product->current_stock = 100;
        $this->product->status = 'active';
        $this->product->save();

        $response = $this->postJson('/api/v1/BranchTransfer/new', [
            'data' => [
                'values' => [
                    'branchId' => $this->branchA->id,
                    'transferDate' => now()->toDateString(),
                ],
                'relatedRecords' => [
                    'items' => [
                        ['productId' => $this->product->id, 'quantity' => 5, 'unit' => 'pcs'],
                    ],
                ],
            ],
        ], ['Idempotency-Key' => 'auth-hardening-pcs-needs-pieces']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['data.relatedRecords.items.0.pieces']);
    }

    public function test_dashboard_summary_filters_by_branch_header(): void
    {
        Sanctum::actingAs($this->admin);

        $billA = new Billing();
        $billA->organization_id = $this->org->id;
        $billA->branch_id = $this->branchA->id;
        $billA->bill_number = 'BILL-SALES-A';
        $billA->payment_status = 'Paid';
        $billA->payment_method = 'Cash';
        $billA->billing_date = now();
        $billA->sub_total = 100;
        $billA->grand_total = 100;
        $billA->save();

        $billB = new Billing();
        $billB->organization_id = $this->org->id;
        $billB->branch_id = $this->branchB->id;
        $billB->bill_number = 'BILL-SALES-B';
        $billB->payment_status = 'Paid';
        $billB->payment_method = 'Cash';
        $billB->billing_date = now();
        $billB->sub_total = 250;
        $billB->grand_total = 250;
        $billB->save();

        $resA = $this->withHeader('X-Branch-Id', (string) $this->branchA->id)
            ->getJson('/api/v1/Dashboard/Summary');
        $resA->assertSuccessful();
        $this->assertEquals(100.0, (float) ($resA->json('data.kpis.salesToday') ?? 0));

        $resB = $this->withHeader('X-Branch-Id', (string) $this->branchB->id)
            ->getJson('/api/v1/Dashboard/Summary');
        $resB->assertSuccessful();
        $this->assertEquals(250.0, (float) ($resB->json('data.kpis.salesToday') ?? 0));

        $resAll = $this->flushHeaders()->getJson('/api/v1/Dashboard/Summary');
        $resAll->assertSuccessful();
        $this->assertEquals(350.0, (float) ($resAll->json('data.kpis.salesToday') ?? 0));
    }

    public function test_admin_billing_list_scopes_by_branch_header(): void
    {
        Sanctum::actingAs($this->admin);

        $billA = new Billing();
        $billA->organization_id = $this->org->id;
        $billA->branch_id = $this->branchA->id;
        $billA->bill_number = 'BILL-LIST-A';
        $billA->payment_status = 'Paid';
        $billA->payment_method = 'Cash';
        $billA->billing_date = now();
        $billA->sub_total = 10;
        $billA->grand_total = 10;
        $billA->save();

        $billB = new Billing();
        $billB->organization_id = $this->org->id;
        $billB->branch_id = $this->branchB->id;
        $billB->bill_number = 'BILL-LIST-B';
        $billB->payment_status = 'Paid';
        $billB->payment_method = 'Cash';
        $billB->billing_date = now();
        $billB->sub_total = 20;
        $billB->grand_total = 20;
        $billB->save();

        $resA = $this->withHeader('X-Branch-Id', (string) $this->branchA->id)
            ->getJson('/api/v1/Billing');
        $resA->assertSuccessful();
        $idsA = collect($resA->json('data.list') ?? [])->pluck('id')->all();
        $this->assertContains($billA->id, $idsA);
        $this->assertNotContains($billB->id, $idsA);

        $resB = $this->withHeader('X-Branch-Id', (string) $this->branchB->id)
            ->getJson('/api/v1/Billing');
        $resB->assertSuccessful();
        $idsB = collect($resB->json('data.list') ?? [])->pluck('id')->all();
        $this->assertContains($billB->id, $idsB);
        $this->assertNotContains($billA->id, $idsB);
    }

    public function test_pending_to_paid_cannot_drain_other_branch_stock(): void
    {
        Sanctum::actingAs($this->staff);

        BranchStock::create([
            'organization_id' => $this->org->id,
            'branch_id' => $this->branchA->id,
            'product_id' => $this->product->id,
            'current_stock' => 10,
        ]);
        BranchStock::create([
            'organization_id' => $this->org->id,
            'branch_id' => $this->branchB->id,
            'product_id' => $this->product->id,
            'current_stock' => 10,
        ]);

        $pending = new Billing();
        $pending->organization_id = $this->org->id;
        $pending->branch_id = $this->branchA->id;
        $pending->bill_number = 'BILL-HOLD-A';
        $pending->payment_status = 'Pending';
        $pending->payment_method = 'Cash';
        $pending->billing_date = now();
        $pending->sub_total = 20;
        $pending->grand_total = 20;
        $pending->save();

        \App\Modules\Api\V1\Billing\Models\BillingItem::create([
            'billing_id' => $pending->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 20,
            'total_price' => 20,
            'unit' => 'pcs',
            'category' => 'bakery',
        ]);

        $response = $this->postJson('/api/v1/Billing/' . $pending->id, [
            'data' => [
                'values' => [
                    'branchId' => $this->branchB->id,
                    'paymentStatus' => 'paid',
                    'paymentMethod' => 'cash',
                ],
            ],
        ], ['Idempotency-Key' => 'cross-branch-pay-attack-1']);

        $response->assertStatus(400);
        $this->assertEquals(10.0, (float) BranchStock::where('branch_id', $this->branchB->id)->value('current_stock'));
        $this->assertEquals(10.0, (float) BranchStock::where('branch_id', $this->branchA->id)->value('current_stock'));
        $this->assertEquals('Pending', Billing::find($pending->id)->payment_status);
    }
}
