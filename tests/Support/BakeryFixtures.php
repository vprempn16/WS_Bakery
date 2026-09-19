<?php

namespace Tests\Support;

use App\Modules\Api\V1\Product\Models\Product;

/**
 * Valid bakery products under current business rules:
 * own products need shelf_life; bought products need expiry_date (API).
 */
class BakeryFixtures
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function ownProduct(string $organizationId, array $overrides = []): Product
    {
        $product = new Product();
        $product->organization_id = $organizationId;
        $product->name = $overrides['name'] ?? 'Bread';
        $product->price = $overrides['price'] ?? 40;
        $product->unit = $overrides['unit'] ?? 'pcs';
        $product->category = $overrides['category'] ?? 'bakery';
        $product->product_source = 'own';
        $product->shelf_life = (int) ($overrides['shelf_life'] ?? 24);
        $product->status = $overrides['status'] ?? 'active';
        $product->current_stock = $overrides['current_stock'] ?? 0;
        if (array_key_exists('product_number', $overrides)) {
            $product->product_number = $overrides['product_number'];
        }
        $product->save();

        return $product;
    }
}
