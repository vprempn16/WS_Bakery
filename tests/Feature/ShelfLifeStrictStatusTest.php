<?php

namespace Tests\Feature;

use App\Modules\Api\V1\Organization\Models\Organization;
use App\Services\ShelfLifeStatusService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShelfLifeStrictStatusTest extends TestCase
{
    use RefreshDatabase;

    private string $orgId;

    private string $productId;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::create(['name' => 'Shelf Strict Bakery']);
        $this->orgId = (string) $org->id;

        $userId = (string) Str::uuid();
        \Illuminate\Support\Facades\DB::table('users')->insert([
            'id' => $userId,
            'organization_id' => $this->orgId,
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'shelf-strict-'.Str::random(6).'@example.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->productId = (string) Str::uuid();
        \Illuminate\Support\Facades\DB::table('products')->insert([
            'id' => $this->productId,
            'organization_id' => $this->orgId,
            'product_number' => '8801',
            'name' => 'Veg Puff',
            'price' => 25,
            'unit' => 'pcs',
            'category' => 'snack',
            'product_source' => 'own',
            'status' => 'active',
            'current_stock' => 14,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->userId = $userId;
    }

    private string $userId;

    private function insertBatch(string $number, float $qty, Carbon $expiry, string $status): void
    {
        \Illuminate\Support\Facades\DB::table('production_batches')->insert([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->orgId,
            'product_id' => $this->productId,
            'batch_number' => $number,
            'quantity_produced' => $qty,
            'production_date' => $expiry->copy()->subDays(2)->toDateString(),
            'expiry_timestamp' => $expiry->toDateTimeString(),
            'status' => $status,
            'created_by' => $this->userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_expired_plus_new_batch_stays_expired_with_fresh_lot_flag(): void
    {
        $this->insertBatch('OLD-1', 4, Carbon::now()->subDay(), 'completed');
        $this->insertBatch('NEW-1', 10, Carbon::now()->addDays(2), 'completed');

        $map = ShelfLifeStatusService::statusForProducts($this->orgId, [$this->productId]);
        $info = $map[$this->productId];

        $this->assertSame(ShelfLifeStatusService::STATUS_EXPIRED, $info['shelfStatus']);
        $this->assertTrue($info['hasFreshLot']);
        $this->assertEquals(4.0, (float) $info['expiredQty']);
    }

    public function test_adding_fresh_stock_does_not_inflate_expired_qty(): void
    {
        // Historical past production 60pcs; on-hand was 60; add 10 fresh → stock 70.
        // Expired badge must stay 60 (not min(pastSum, 70)=70).
        \Illuminate\Support\Facades\DB::table('products')
            ->where('id', $this->productId)
            ->update(['current_stock' => 70]);

        $this->insertBatch('OLD-60', 60, Carbon::now()->subDay(), 'completed');
        $this->insertBatch('NEW-10', 10, Carbon::now()->addDays(2), 'completed');

        $map = ShelfLifeStatusService::statusForProducts($this->orgId, [$this->productId]);
        $info = $map[$this->productId];

        $this->assertSame(ShelfLifeStatusService::STATUS_EXPIRED, $info['shelfStatus']);
        $this->assertTrue($info['hasFreshLot']);
        $this->assertEquals(60.0, (float) $info['expiredQty']);
    }

    public function test_after_marking_expired_lots_wasted_status_becomes_fresh(): void
    {
        $this->insertBatch('OLD-2', 4, Carbon::now()->subDay(), 'completed');
        $this->insertBatch('NEW-2', 10, Carbon::now()->addDays(2), 'completed');

        ShelfLifeStatusService::markExpiredLotsWasted($this->orgId, $this->productId, 4.0);

        $oldStatus = \Illuminate\Support\Facades\DB::table('production_batches')
            ->where('batch_number', 'OLD-2')
            ->value('status');
        $this->assertSame('wasted', strtolower((string) $oldStatus));

        $map = ShelfLifeStatusService::statusForProducts($this->orgId, [$this->productId]);
        $info = $map[$this->productId];

        $this->assertSame(ShelfLifeStatusService::STATUS_FRESH, $info['shelfStatus']);
        $this->assertEquals(0.0, (float) $info['expiredQty']);
        $this->assertTrue($info['hasFreshLot']);
    }

    public function test_after_returning_expired_qty_remaining_stock_is_fresh_only(): void
    {
        \Illuminate\Support\Facades\DB::table('products')
            ->where('id', $this->productId)
            ->update(['current_stock' => 200]);

        $this->insertBatch('OLD-180', 180, Carbon::now()->subDay(), 'completed');
        $this->insertBatch('NEW-20', 20, Carbon::now()->addDays(2), 'completed');

        $before = ShelfLifeStatusService::statusForProducts(
            $this->orgId,
            [$this->productId],
            [$this->productId => 200.0]
        )[$this->productId];
        $this->assertSame(ShelfLifeStatusService::STATUS_EXPIRED, $before['shelfStatus']);
        $this->assertEquals(180.0, (float) $before['expiredQty']);
        $this->assertTrue($before['hasFreshLot']);

        ShelfLifeStatusService::markExpiredLotsWasted($this->orgId, $this->productId, 180.0);

        $after = ShelfLifeStatusService::statusForProducts(
            $this->orgId,
            [$this->productId],
            [$this->productId => 20.0]
        )[$this->productId];
        $this->assertSame(ShelfLifeStatusService::STATUS_FRESH, $after['shelfStatus']);
        $this->assertEquals(0.0, (float) $after['expiredQty']);
        $this->assertTrue($after['hasFreshLot']);
    }

    public function test_cancelled_expired_batch_is_ignored(): void
    {
        $this->insertBatch('CAN-1', 4, Carbon::now()->subDay(), 'cancelled');

        $map = ShelfLifeStatusService::statusForProducts($this->orgId, [$this->productId]);
        $info = $map[$this->productId];

        $this->assertSame(ShelfLifeStatusService::STATUS_FRESH, $info['shelfStatus']);
        $this->assertEquals(0.0, (float) $info['expiredQty']);
    }

    public function test_status_for_timestamp_classifies_fresh_expiring_expired(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 12:00:00'));

        $fresh = ShelfLifeStatusService::statusForTimestamp(Carbon::parse('2026-09-20 18:30:00'));
        $this->assertSame(ShelfLifeStatusService::STATUS_FRESH, $fresh['shelfStatus']);

        $soon = ShelfLifeStatusService::statusForTimestamp(Carbon::parse('2026-09-16 06:00:00'));
        $this->assertSame(ShelfLifeStatusService::STATUS_EXPIRING, $soon['shelfStatus']);

        $expired = ShelfLifeStatusService::statusForTimestamp(Carbon::parse('2026-09-14 18:30:00'));
        $this->assertSame(ShelfLifeStatusService::STATUS_EXPIRED, $expired['shelfStatus']);

        Carbon::setTestNow();
    }
}
