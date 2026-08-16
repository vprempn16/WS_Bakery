<?php

namespace Tests\Feature;

use App\Modules\Api\V1\Branch\Models\Branch;
use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use App\Modules\Api\V1\BranchTransfer\Models\BranchTransfer;
use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\ProductionBatch\Models\ProductionBatch;
use App\Modules\Api\V1\User\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DateRangeListFilterTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private Branch $branch;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['name' => 'Date Filter Bakery']);
        $this->admin = User::create([
            'organization_id' => $this->org->id,
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin-date-filter@example.com',
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);
        $this->branch = new Branch();
        $this->branch->organization_id = $this->org->id;
        $this->branch->name = 'Main';
        $this->branch->type = 'warehouse';
        $this->branch->save();

        $this->product = new Product();
        $this->product->organization_id = $this->org->id;
        $this->product->name = 'Cake';
        $this->product->price = 50;
        $this->product->unit = 'pcs';
        $this->product->category = 'bakery';
        $this->product->current_stock = 0;
        $this->product->save();
    }

    public function test_production_batch_date_from_to_filters_production_date(): void
    {
        Sanctum::actingAs($this->admin);

        $todayBatch = new ProductionBatch();
        $todayBatch->organization_id = $this->org->id;
        $todayBatch->product_id = $this->product->id;
        $todayBatch->batch_number = 'PB-TODAY';
        $todayBatch->quantity_produced = 1;
        $todayBatch->production_date = now()->toDateString();
        $todayBatch->status = 'completed';
        $todayBatch->created_by = $this->admin->id;
        $todayBatch->save();

        $oldBatch = new ProductionBatch();
        $oldBatch->organization_id = $this->org->id;
        $oldBatch->product_id = $this->product->id;
        $oldBatch->batch_number = 'PB-OLD';
        $oldBatch->quantity_produced = 1;
        $oldBatch->production_date = now()->subDays(5)->toDateString();
        $oldBatch->status = 'completed';
        $oldBatch->created_by = $this->admin->id;
        $oldBatch->save();

        $today = now()->toDateString();
        $this->getJson("/api/v1/ProductionBatch?dateFrom={$today}&dateTo={$today}")
            ->assertSuccessful()
            ->assertJsonPath('data.meta.total', 1);

        $this->getJson('/api/v1/ProductionBatch')
            ->assertSuccessful()
            ->assertJsonPath('data.meta.total', 2);
    }

    public function test_branch_stock_date_from_to_filters_updated_at(): void
    {
        Sanctum::actingAs($this->admin);

        $fresh = BranchStock::create([
            'organization_id' => $this->org->id,
            'branch_id' => $this->branch->id,
            'product_id' => $this->product->id,
            'current_stock' => 5,
        ]);

        $staleProduct = new Product();
        $staleProduct->organization_id = $this->org->id;
        $staleProduct->name = 'Old Cake';
        $staleProduct->price = 40;
        $staleProduct->unit = 'pcs';
        $staleProduct->category = 'bakery';
        $staleProduct->current_stock = 0;
        $staleProduct->save();

        $stale = BranchStock::create([
            'organization_id' => $this->org->id,
            'branch_id' => $this->branch->id,
            'product_id' => $staleProduct->id,
            'current_stock' => 3,
        ]);
        BranchStock::where('id', $stale->id)->update([
            'updated_at' => Carbon::now()->subDays(10),
        ]);

        $today = now()->toDateString();
        $this->withHeader('X-Branch-Id', (string) $this->branch->id)
            ->getJson("/api/v1/BranchStock?dateFrom={$today}&dateTo={$today}")
            ->assertSuccessful()
            ->assertJsonPath('data.meta.total', 1);

        $this->withHeader('X-Branch-Id', (string) $this->branch->id)
            ->getJson('/api/v1/BranchStock')
            ->assertSuccessful()
            ->assertJsonPath('data.meta.total', 2);

        $this->assertNotNull($fresh->id);
    }

    public function test_branch_transfer_date_from_to_filters_transfer_date(): void
    {
        Sanctum::actingAs($this->admin);

        $dest = new Branch();
        $dest->organization_id = $this->org->id;
        $dest->name = 'Retail';
        $dest->type = 'retail';
        $dest->save();

        $todayTransfer = new BranchTransfer();
        $todayTransfer->organization_id = $this->org->id;
        $todayTransfer->branch_id = $dest->id;
        $todayTransfer->transfer_number = 'TR-TODAY';
        $todayTransfer->transfer_date = now()->toDateString();
        $todayTransfer->status = 'completed';
        $todayTransfer->created_by = $this->admin->id;
        $todayTransfer->save();

        $oldTransfer = new BranchTransfer();
        $oldTransfer->organization_id = $this->org->id;
        $oldTransfer->branch_id = $dest->id;
        $oldTransfer->transfer_number = 'TR-OLD';
        $oldTransfer->transfer_date = now()->subDays(8)->toDateString();
        $oldTransfer->status = 'completed';
        $oldTransfer->created_by = $this->admin->id;
        $oldTransfer->save();

        $today = now()->toDateString();
        $this->getJson("/api/v1/BranchTransfer?dateFrom={$today}&dateTo={$today}")
            ->assertSuccessful()
            ->assertJsonPath('data.meta.total', 1);

        $this->getJson('/api/v1/BranchTransfer')
            ->assertSuccessful()
            ->assertJsonPath('data.meta.total', 2);
    }

    public function test_billing_date_from_to_filters_billing_date(): void
    {
        Sanctum::actingAs($this->admin);

        $todayBill = new \App\Modules\Api\V1\Billing\Models\Billing();
        $todayBill->organization_id = $this->org->id;
        $todayBill->branch_id = $this->branch->id;
        $todayBill->bill_number = 'BILL-TODAY';
        $todayBill->payment_status = 'Paid';
        $todayBill->payment_method = 'Cash';
        $todayBill->billing_date = now()->toDateString();
        $todayBill->sub_total = 50;
        $todayBill->grand_total = 50;
        $todayBill->save();

        $oldBill = new \App\Modules\Api\V1\Billing\Models\Billing();
        $oldBill->organization_id = $this->org->id;
        $oldBill->branch_id = $this->branch->id;
        $oldBill->bill_number = 'BILL-OLD';
        $oldBill->payment_status = 'Paid';
        $oldBill->payment_method = 'Cash';
        $oldBill->billing_date = now()->subDays(4)->toDateString();
        $oldBill->sub_total = 40;
        $oldBill->grand_total = 40;
        $oldBill->save();

        $today = now()->toDateString();
        $this->withHeader('X-Branch-Id', (string) $this->branch->id)
            ->getJson("/api/v1/Billing?dateFrom={$today}&dateTo={$today}")
            ->assertSuccessful()
            ->assertJsonPath('data.meta.total', 1);

        $this->withHeader('X-Branch-Id', (string) $this->branch->id)
            ->getJson('/api/v1/Billing')
            ->assertSuccessful()
            ->assertJsonPath('data.meta.total', 2);
    }
}
