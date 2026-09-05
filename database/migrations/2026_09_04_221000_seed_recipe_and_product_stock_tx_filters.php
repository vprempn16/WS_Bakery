<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Default column filters for Recipe + ProductStockTransaction lists.
     */
    public function up(): void
    {
        $now = now();
        $defs = [
            'recipes' => [
                ['fieldname' => 'productId', 'fieldlabel' => 'Product'],
                ['fieldname' => 'ingredientId', 'fieldlabel' => 'Ingredient'],
                ['fieldname' => 'quantityRequired', 'fieldlabel' => 'Quantity Required'],
            ],
            'product_stock_transactions' => [
                ['fieldname' => 'productId', 'fieldlabel' => 'Product'],
                ['fieldname' => 'type', 'fieldlabel' => 'Type'],
                ['fieldname' => 'quantity', 'fieldlabel' => 'Quantity'],
                ['fieldname' => 'referenceNote', 'fieldlabel' => 'Reference Note'],
            ],
        ];

        foreach ($defs as $module => $headers) {
            $exists = DB::table('saved-filters')
                ->where('module', $module)
                ->where('is_default', true)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('saved-filters')->insert([
                'id' => Str::uuid()->toString(),
                'organization_id' => null,
                'user_id' => null,
                'name' => 'All',
                'module' => $module,
                'rules' => json_encode([]),
                'is_public' => true,
                'is_default' => true,
                'header_details' => json_encode($headers),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('saved-filters')
            ->whereIn('module', ['recipes', 'product_stock_transactions'])
            ->where('is_default', true)
            ->whereNull('organization_id')
            ->delete();
    }
};
