<?php

namespace App\Modules\Api\V1\Billing\Services;

/**
 * Server-side billing math — must stay aligned with bk-frontend billingCalc.ts.
 *
 * Weight products (gm/kg/g): quantity is grams, catalog price is per kg.
 * Piece products: quantity × catalog unit price.
 */
class BillingPriceService
{
    public static function isWeightUnit(?string $unit): bool
    {
        $u = strtolower(trim((string) $unit));

        return in_array($u, ['kg', 'gm', 'g'], true);
    }

    /**
     * Compute line total for one bill item.
     */
    public static function lineTotal(float $quantity, float $catalogPrice, ?string $unit): float
    {
        if ($quantity <= 0) {
            return 0.0;
        }

        if (self::isWeightUnit($unit)) {
            // qty in grams, price per kg → (grams / 1000) × pricePerKg
            return round(($quantity / 1000) * $catalogPrice, 2);
        }

        return round($quantity * $catalogPrice, 2);
    }
}
