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
        ]);

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
}
