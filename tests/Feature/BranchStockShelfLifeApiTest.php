<?php

namespace Tests\Feature;

use App\Modules\Api\V1\Branch\Models\Branch;
use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\User\Models\User;
use App\Services\DefaultStaffProfilesService;
use App\Services\ShelfLifeStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * BranchStock list must return camelCase shelf fields so POS, Return,
 * BranchStock list, and Dashboard BranchShelfLife stay aligned.
 */
class BranchStockShelfLifeApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Branch $retail;

    private Product $product;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['name' => 'Shelf Align Bakery']);

        $this->retail = new Branch();
        $this->retail->organization_id = $this->org->id;
        $this->retail->name = 'Supreme';
        $this->retail->type = 'retail';
        $this->retail->save();

        $this->product = new Product();
        $this->product->organization_id = $this->org->id;
        $this->product->name = 'Veg Puff';
        $this->product->price = 25;
        $this->product->unit = 'pcs';
        $this->product->category = 'snack';
        $this->product->status = 'active';
        $this->product->product_source = 'own';
        $this->product->current_stock = 0;
        $this->product->save();

        BranchStock::create([
            'organization_id' => $this->org->id,
            'branch_id' => $this->retail->id,
            'product_id' => $this->product->id,
            'current_stock' => 50,
        ]);

        $this->admin = User::create([
            'organization_id' => $this->org->id,
            'branch_id' => $this->retail->id,
            'first_name' => 'Admin',
            'last_name' => 'Align',
            'email' => 'admin-shelf-align@example.com',
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);

        app(DefaultStaffProfilesService::class)->ensureForOrganization($this->org->id, $this->admin->id);

        DB::table('production_batches')->insert([
            [
                'id' => (string) Str::uuid(),
                'organization_id' => $this->org->id,
                'product_id' => $this->product->id,
                'batch_number' => 'ALIGN-EXPIRED-30',
                'quantity_produced' => 30,
                'production_date' => now()->subDays(3)->toDateString(),
                'expiry_timestamp' => now()->subDay()->toDateTimeString(),
                'status' => 'completed',
                'created_by' => $this->admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'organization_id' => $this->org->id,
                'product_id' => $this->product->id,
                'batch_number' => 'ALIGN-FRESH-20',
                'quantity_produced' => 20,
                'production_date' => now()->toDateString(),
                'expiry_timestamp' => now()->addDays(2)->toDateTimeString(),
                'status' => 'completed',
                'created_by' => $this->admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function test_branch_stock_list_returns_camel_case_shelf_fields(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->withHeaders([
            'X-Branch-Id' => (string) $this->retail->id,
        ])->getJson('/api/v1/BranchStock?per_page=50');

        $response->assertStatus(200);
        $list = $response->json('data.list') ?? [];
        $this->assertNotEmpty($list);

        $row = collect($list)->first(function ($item) {
            $productId = $item['productId'] ?? $item['product_id'] ?? null;

            return (string) $productId === (string) $this->product->id;
        });

        $this->assertNotNull($row, 'Expected BranchStock row for product');
        $this->assertArrayHasKey('currentStock', $row);
        $this->assertArrayHasKey('expiredQty', $row);
        $this->assertArrayHasKey('shelfStatus', $row);
        $this->assertEquals(50.0, (float) $row['currentStock']);
        $this->assertEquals(30.0, (float) $row['expiredQty']);
        $this->assertSame(ShelfLifeStatusService::STATUS_EXPIRED, $row['shelfStatus']);
    }

    public function test_pos_dashboard_and_branch_stock_agree_on_expired_qty(): void
    {
        Sanctum::actingAs($this->admin);
        $headers = ['X-Branch-Id' => (string) $this->retail->id];

        $stockRes = $this->withHeaders($headers)->getJson('/api/v1/BranchStock?per_page=50');
        $posRes = $this->withHeaders($headers)->getJson('/api/v1/Billing/pos-products?per_page=50');
        $dashRes = $this->withHeaders($headers)->getJson('/api/v1/Reports/BranchShelfLife');

        $stockRes->assertStatus(200);
        $posRes->assertStatus(200);
        $dashRes->assertStatus(200);

        $stockRow = collect($stockRes->json('data.list') ?? [])->first(
            fn ($item) => (string) ($item['productId'] ?? '') === (string) $this->product->id
        );
        $posRow = collect($posRes->json('data.list') ?? [])->first(
            fn ($item) => (string) ($item['id'] ?? '') === (string) $this->product->id
        );
        $dashRow = collect($dashRes->json('data.products') ?? [])->first(
            fn ($item) => (string) ($item['productId'] ?? '') === (string) $this->product->id
        );

        $this->assertNotNull($stockRow);
        $this->assertNotNull($posRow);
        $this->assertNotNull($dashRow);

        $this->assertEquals(30.0, (float) $stockRow['expiredQty']);
        $this->assertEquals(30.0, (float) $posRow['expiredQty']);
        $this->assertEquals(30.0, (float) $dashRow['expiredQty']);
        $this->assertSame(ShelfLifeStatusService::STATUS_EXPIRED, $stockRow['shelfStatus']);
        $this->assertSame(ShelfLifeStatusService::STATUS_EXPIRED, $posRow['shelfStatus']);
        $this->assertSame(ShelfLifeStatusService::STATUS_EXPIRED, $dashRow['shelfStatus']);
        $this->assertGreaterThanOrEqual(1, (int) ($dashRes->json('data.summary.expiredCount') ?? 0));
    }

    public function test_pos_shows_no_expired_when_branch_stock_empty(): void
    {
        // Org still has expired production lots, but this retail branch has zero ledger.
        BranchStock::where('branch_id', $this->retail->id)
            ->where('product_id', $this->product->id)
            ->delete();

        // Product master stock can be non-zero — must not leak into POS shelf gate.
        $this->product->current_stock = 100;
        $this->product->save();

        Sanctum::actingAs($this->admin);

        $posRes = $this->withHeaders([
            'X-Branch-Id' => (string) $this->retail->id,
        ])->getJson('/api/v1/Billing/pos-products?per_page=50');

        $dashRes = $this->withHeaders([
            'X-Branch-Id' => (string) $this->retail->id,
        ])->getJson('/api/v1/Reports/BranchShelfLife');

        $posRes->assertStatus(200);
        $dashRes->assertStatus(200);

        $posRow = collect($posRes->json('data.list') ?? [])->first(
            fn ($item) => (string) ($item['id'] ?? '') === (string) $this->product->id
        );

        $this->assertNotNull($posRow);
        $this->assertEquals(0.0, (float) ($posRow['currentStock'] ?? 0));
        $this->assertEquals(0.0, (float) ($posRow['expiredQty'] ?? 0));
        $this->assertTrue(
            ($posRow['shelfStatus'] ?? null) === null
            || (float) ($posRow['expiredQty'] ?? 0) === 0.0
        );

        $this->assertEquals(0, (int) ($dashRes->json('data.summary.expiredCount') ?? 0));
        $this->assertEmpty($dashRes->json('data.products') ?? []);
    }
}
