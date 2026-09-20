<?php

namespace App\Services;

use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use App\Modules\Api\V1\InventoryTransaction\Models\InventoryTransaction;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\Recipe\Models\Recipe;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Loads the client-pvt warehouse catalog (ingredients + sample products/recipes)
 * for an organization. Does not create production batches, POS bills, or transfers.
 */
class DefaultCatalogService
{
    private const PACKAGING_CATEGORIES = [
        'packaging',
        'cake_packaging',
        'food_packing_covers',
        'carry_bags',
    ];

    /**
     * Previous / alternate English names keyed by normalized canonical English prefix.
     * Used so re-seeding renames existing rows instead of creating duplicates.
     *
     * @return array<string, list<string>>
     */
    private function ingredientAliasMap(): array
    {
        return [
            'ghee' => ['Ghee'],
            'maida / wheat flour' => ['Maida Flour', 'Maida / Wheat Flour'],
            'corn flour' => ['Corn Flour'],
            'besan / gram flour' => ['Besan / Gram Flour'],
            'ragi flour' => ['Ragi Flour'],
            'urad dal' => ['Urad Dal'],
            'chana dal / split chickpeas' => ['Chana Dal', 'Chana Dal / Split Chickpeas'],
            'moong dal' => ['Moong Dal'],
            'green gram' => ['Green Gram'],
            'chickpeas' => ['Chickpeas'],
            'peanut' => ['Groundnut', 'Peanut'],
            'sesame seeds' => ['White Sesame Seeds', 'Sesame Seeds'],
            'milk powder' => ['Milk Powder'],
            'coconut powder' => ['Coconut Powder'],
            'desiccated coconut' => ['Coconut Copra', 'Desiccated Coconut'],
            'yeast' => ['Angel Yeast', 'Yeast'],
            'cocoa powder' => ['Cocoa Powder'],
            'vanilla essence' => ['Vanilla Essence'],
            'sugar' => ['White Crystal Sugar', 'Sugar'],
            'jaggery' => ['Country Sugar / Jaggery', 'Jaggery'],
            'vanaspati 1 litre' => ['Vanaspati 1 Litre'],
            'vanaspati 1/2 litre' => ['Vanaspati 1/2 Litre'],
            'cake gel' => ['Cake gel', 'Cake Gel'],
            'chicken masala' => ['Chiken Masala', 'Chicken Masala'],
            'orange emulsion essence' => ['Orange emulsion essence', 'Orange Emulsion', 'Orange emulsion'],
            'egg' => ['Egg'],
            'dalda' => ['Dalda'],
            'chilli powder' => ['Chilli Powder', 'Vatha Podi'],
        ];
    }

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
        $claimedIds = [];

        /** @var Collection<int, Ingredient> $existing */
        $existing = Ingredient::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->get();

        foreach ($defs as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $unit = strtolower(trim((string) ($row['unit'] ?? 'gm')));
            if ($unit === 'g') {
                $unit = 'gm';
            }

            $category = strtolower(trim((string) ($row['category'] ?? 'raw')));
            $minStock = (float) ($row['min'] ?? ($unit === 'pcs' ? 10 : 5000));

            $ingredient = $this->findExistingIngredient($existing, $name, $claimedIds);

            if ($ingredient) {
                $claimedIds[$ingredient->id] = true;
                $ingredient->name = $name;
                if (in_array($category, self::PACKAGING_CATEGORIES, true)) {
                    $ingredient->category = $category;
                }
                if ($vendorId) {
                    $ingredient->vendor_id = $vendorId;
                }
                $ingredient->save();
            } else {
                $ingredient = new Ingredient();
                $ingredient->organization_id = $organizationId;
                $ingredient->name = $name;
                $ingredient->unit = $unit;
                $ingredient->category = $category;
                $ingredient->minimum_stock_level = $minStock;
                if ($vendorId) {
                    $ingredient->vendor_id = $vendorId;
                }
                $ingredient->save();
                $existing->push($ingredient);
                $claimedIds[$ingredient->id] = true;
            }

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

    /**
     * @param  Collection<int, Ingredient>  $existing
     * @param  array<string, true>  $claimedIds
     */
    private function findExistingIngredient(Collection $existing, string $canonicalName, array $claimedIds): ?Ingredient
    {
        $candidates = $this->matchCandidatesFor($canonicalName);
        $candidateKeys = [];
        foreach ($candidates as $candidate) {
            $candidateKeys[$this->normalizeIngredientName($candidate)] = true;
        }

        foreach ($existing as $ingredient) {
            if (isset($claimedIds[$ingredient->id])) {
                continue;
            }
            $key = $this->normalizeIngredientName((string) $ingredient->name);
            if (isset($candidateKeys[$key])) {
                return $ingredient;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function matchCandidatesFor(string $canonicalName): array
    {
        $english = $this->englishPrefix($canonicalName);
        $candidates = [$canonicalName, $english];

        $aliasKey = $this->normalizeIngredientName($english);
        foreach ($this->ingredientAliasMap()[$aliasKey] ?? [] as $alias) {
            $candidates[] = $alias;
        }

        return array_values(array_unique($candidates));
    }

    private function englishPrefix(string $name): string
    {
        if (preg_match('/^(.+?)\s*\([^)]*[\x{0B80}-\x{0BFF}][^)]*\)\s*$/u', $name, $m)) {
            return trim($m[1]);
        }

        return trim($name);
    }

    private function normalizeIngredientName(string $name): string
    {
        $english = $this->englishPrefix($name);
        $normalized = mb_strtolower($english, 'UTF-8');
        $normalized = str_replace(['½', '1½', '1/2'], ['1/2', '1 1/2', '1/2'], $normalized);
        $normalized = preg_replace('/[^a-z0-9\/\s]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
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

        /** @var Collection<int, Product> $existingProducts */
        $existingProducts = Product::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->get();

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

            $existing = $existingProducts->firstWhere('product_number', $number);
            if (! $existing) {
                $nameCandidates = array_merge([$name], is_array($row['aliases'] ?? null) ? $row['aliases'] : []);
                $nameKeys = [];
                foreach ($nameCandidates as $candidate) {
                    $nameKeys[$this->normalizeIngredientName((string) $candidate)] = true;
                }
                $existing = $existingProducts->first(function (Product $p) use ($nameKeys) {
                    return isset($nameKeys[$this->normalizeIngredientName((string) $p->name)]);
                });
            }

            if ($existing) {
                DB::table('products')->where('id', $existing->id)->update($values);
                $product = Product::withoutGlobalScopes()->findOrFail($existing->id);
                $existing->name = $name;
                $existing->product_number = $number;
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
                $existingProducts->push($product);
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
        $hasPendingCol = Schema::hasColumn('recipes', 'quantity_pending');

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
                $ingredient = $this->resolveIngredientFromMap($ingredients, $ingName);
                if (! $ingredient) {
                    throw new \RuntimeException("client-pvt recipe missing ingredient '{$ingName}' for product '{$productName}'.");
                }

                $pending = ! empty($line['qty_pending']);
                if ($pending) {
                    $payload = [
                        'quantity_required' => null,
                    ];
                    if ($hasPendingCol) {
                        $payload['quantity_pending'] = true;
                    }
                } else {
                    if (array_key_exists('qty_per_kg', $line)) {
                        $qty = round(((float) $line['qty_per_kg']) / 1000, 4);
                    } else {
                        $qty = (float) ($line['qty'] ?? 0);
                    }
                    if ($qty < 0.01) {
                        $qty = 0.01;
                    }
                    $payload = [
                        'quantity_required' => $qty,
                    ];
                    if ($hasPendingCol) {
                        $payload['quantity_pending'] = false;
                    }
                }

                $expectedIngredientIds[] = $ingredient->id;
                Recipe::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'ingredient_id' => $ingredient->id,
                    ],
                    $payload
                );
            }

            Recipe::where('product_id', $product->id)
                ->whereNotIn('ingredient_id', $expectedIngredientIds)
                ->delete();
        }
    }

    /**
     * @param  array<string, Ingredient>  $ingredients
     */
    private function resolveIngredientFromMap(array $ingredients, string $name): ?Ingredient
    {
        if (isset($ingredients[$name])) {
            return $ingredients[$name];
        }

        $want = $this->normalizeIngredientName($name);
        foreach ($ingredients as $ingredient) {
            if ($this->normalizeIngredientName((string) $ingredient->name) === $want) {
                return $ingredient;
            }
        }

        foreach ($this->matchCandidatesFor($name) as $candidate) {
            foreach ($ingredients as $key => $ingredient) {
                if ($this->normalizeIngredientName((string) $key) === $this->normalizeIngredientName($candidate)
                    || $this->normalizeIngredientName((string) $ingredient->name) === $this->normalizeIngredientName($candidate)) {
                    return $ingredient;
                }
            }
        }

        return null;
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
