<?php

namespace App\Services;

use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use App\Modules\Api\V1\InventoryTransaction\Models\InventoryTransaction;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\Recipe\Models\Recipe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Loads the client-pvt warehouse catalog (ingredients + sample products/recipes)
 * for an organization. Does not create production batches, POS bills, or transfers.
 */
class DefaultCatalogService
{
    /**
     * @return array{ingredients: array<string, Ingredient>, products: array<string, Product>}
     */
    public function seedForOrganization(string $organizationId, bool $withOpeningStock = false, ?string $vendorId = null): array
    {
        $ingredients = $this->seedIngredients($organizationId, $withOpeningStock, $vendorId);
        $products = $this->seedProducts($organizationId);
        $this->seedRecipes($products, $ingredients);

        return ['ingredients' => $ingredients, 'products' => $products];
    }

    /**
     * @return array<string, Ingredient>
     */
    private function seedIngredients(string $organizationId, bool $withOpeningStock, ?string $vendorId): array
    {
        $defs = $this->loadPhpList(base_path('client-pvt/ingredients.php'));
        $out = [];

        foreach ($defs as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $unit = strtolower(trim((string) ($row['unit'] ?? 'gm')));
            if ($unit === 'g') {
                $unit = 'gm';
            }

            $payload = [
                'unit' => $unit,
                'category' => strtolower(trim((string) ($row['category'] ?? 'raw'))),
                'minimum_stock_level' => (float) ($row['min'] ?? ($unit === 'pcs' ? 10 : 5000)),
            ];
            if ($vendorId) {
                $payload['vendor_id'] = $vendorId;
            }

            $ingredient = Ingredient::withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('name', $name)
                ->first();

            if (! $ingredient) {
                $ingredient = new Ingredient();
                $ingredient->organization_id = $organizationId;
                $ingredient->name = $name;
            }

            $ingredient->unit = $payload['unit'];
            $ingredient->category = $payload['category'];
            $ingredient->minimum_stock_level = $payload['minimum_stock_level'];
            if ($vendorId) {
                $ingredient->vendor_id = $vendorId;
            }
            $ingredient->save();
            $out[$name] = $ingredient;

            if ($withOpeningStock) {
                $this->ensureOpeningStock(
                    $organizationId,
                    $ingredient,
                    (float) ($row['stock'] ?? ($unit === 'pcs' ? 50 : 50000))
                );
            }
        }

        return $out;
    }

    private function ensureOpeningStock(string $organizationId, Ingredient $ingredient, float $target): void
    {
        if ($target <= 0) {
            return;
        }

        $ingredient = $ingredient->fresh() ?? $ingredient;
        $current = (float) $ingredient->current_stock;
        $need = $target - $current;
        if ($need <= 0) {
            return;
        }

        $tx = new InventoryTransaction();
        $tx->organization_id = $organizationId;
        $tx->ingredient_id = $ingredient->id;
        $tx->type = 'in';
        $tx->quantity = $need;
        $tx->reference_note = 'CLIENT-PVT-STOCK-IN '.$ingredient->name;
        $tx->save();

        $ingredient->current_stock = $current + $need;
        $ingredient->save();
    }

    /**
     * @return array<string, Product>
     */
    private function seedProducts(string $organizationId): array
    {
        $defs = $this->loadPhpList(base_path('client-pvt/products.php'));
        $out = [];
        $hasSource = Schema::hasColumn('products', 'product_source');
        $hasShelf = Schema::hasColumn('products', 'shelf_life');
        $hasExpiry = Schema::hasColumn('products', 'expiry_date');
        $hasStatus = Schema::hasColumn('products', 'status');

        foreach ($defs as $row) {
            $name = (string) ($row['name'] ?? '');
            $number = (string) ($row['number'] ?? '');
            if ($name === '' || $number === '') {
                continue;
            }

            $source = strtolower(trim((string) ($row['source'] ?? 'own')));
            $isBought = $source === 'bought';
            $unit = strtolower(trim((string) ($row['unit'] ?? 'gm')));
            if ($unit === 'g') {
                $unit = 'gm';
            }

            $values = [
                'name' => $name,
                'description' => $row['description'] ?? null,
                'price' => $row['price'] ?? 0,
                'unit' => $unit,
                'category' => $row['category'] ?? 'other',
                'updated_at' => now(),
            ];
            if ($hasStatus) {
                $values['status'] = 'active';
            }
            if ($hasSource) {
                $values['product_source'] = $isBought ? 'bought' : 'own';
            }
            if ($hasShelf) {
                $values['shelf_life'] = $isBought ? null : ($row['shelf_life'] ?? null);
            }
            if ($hasExpiry) {
                $values['expiry_date'] = $isBought ? ($row['expiry_date'] ?? null) : null;
            }

            $existing = Product::withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('product_number', $number)
                ->first();

            if ($existing) {
                DB::table('products')->where('id', $existing->id)->update($values);
                $product = Product::withoutGlobalScopes()->findOrFail($existing->id);
            } else {
                $id = (string) Str::uuid();
                $insert = array_merge($values, [
                    'id' => $id,
                    'organization_id' => $organizationId,
                    'product_number' => $number,
                    'current_stock' => 0,
                    'created_at' => now(),
                ]);
                DB::table('products')->insert($insert);
                $product = Product::withoutGlobalScopes()->findOrFail($id);
            }

            $out[$name] = $product;
        }

        return $out;
    }

    /**
     * @param  array<string, Product>  $products
     * @param  array<string, Ingredient>  $ingredients
     */
    private function seedRecipes(array $products, array $ingredients): void
    {
        $defs = $this->loadPhpList(base_path('client-pvt/products.php'));

        foreach ($defs as $row) {
            $productName = (string) ($row['name'] ?? '');
            $product = $products[$productName] ?? null;
            if (! $product) {
                continue;
            }

            $source = strtolower(trim((string) ($row['source'] ?? 'own')));
            $lines = is_array($row['recipe'] ?? null) ? $row['recipe'] : [];

            if ($source === 'bought' || $lines === []) {
                Recipe::where('product_id', $product->id)->delete();
                continue;
            }

            $expectedIngredientIds = [];
            foreach ($lines as $line) {
                $ingName = (string) ($line['ingredient'] ?? '');
                $ingredient = $ingredients[$ingName] ?? null;
                if (! $ingredient) {
                    throw new \RuntimeException("client-pvt recipe missing ingredient '{$ingName}' for product '{$productName}'.");
                }

                if (array_key_exists('qty_per_kg', $line)) {
                    $qty = round(((float) $line['qty_per_kg']) / 1000, 2);
                } else {
                    $qty = (float) ($line['qty'] ?? 0);
                }
                if ($qty < 0.01) {
                    $qty = 0.01;
                }

                $expectedIngredientIds[] = $ingredient->id;
                Recipe::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'ingredient_id' => $ingredient->id,
                    ],
                    ['quantity_required' => $qty]
                );
            }

            Recipe::where('product_id', $product->id)
                ->whereNotIn('ingredient_id', $expectedIngredientIds)
                ->delete();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadPhpList(string $path): array
    {
        if (! is_file($path)) {
            throw new \RuntimeException("Catalog file missing: {$path}");
        }

        $data = require $path;
        if (! is_array($data)) {
            throw new \RuntimeException("Catalog file must return an array: {$path}");
        }

        return array_values($data);
    }
}
