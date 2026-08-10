<?php

namespace Tests\Unit;

use App\Modules\Api\V1\Billing\Services\BillingPriceService;
use PHPUnit\Framework\TestCase;

class BillingPriceServiceTest extends TestCase
{
    public function test_weight_line_total_250g_at_400_per_kg(): void
    {
        $this->assertEquals(100.0, BillingPriceService::lineTotal(250, 400, 'gm'));
    }

    public function test_weight_line_total_1500g_at_400_per_kg(): void
    {
        $this->assertEquals(600.0, BillingPriceService::lineTotal(1500, 400, 'gm'));
    }

    public function test_piece_line_total(): void
    {
        $this->assertEquals(50.0, BillingPriceService::lineTotal(2, 25, 'pcs'));
    }

    public function test_kg_unit_treated_as_weight(): void
    {
        $this->assertEquals(100.0, BillingPriceService::lineTotal(250, 400, 'kg'));
    }
}
