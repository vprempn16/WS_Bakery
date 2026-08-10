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
}
