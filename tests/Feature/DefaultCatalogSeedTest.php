<?php

namespace Tests\Feature;

use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\ProductionBatch\Models\ProductionBatch;
use App\Modules\Api\V1\Recipe\Models\Recipe;
use App\Services\DefaultCatalogService;
use Database\Seeders\ClientDemoBakerySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DefaultCatalogSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_demo_seeder_loads_client_pvt_catalog_without_batches_or_pos(): void
    {
        $this->seed(ClientDemoBakerySeeder::class);

        $org = Organization::where('email', 'demo@client-bakery.test')->first();
        $this->assertNotNull($org);

        $ingredientDefs = require base_path('client-pvt/ingredients.php');
        $productDefs = require base_path('client-pvt/products.php');

        $ingredients = Ingredient::withoutGlobalScopes()->where('organization_id', $org->id)->get();
        $this->assertCount(count($ingredientDefs), $ingredients);

        $maida = $ingredients->firstWhere('name', 'Maida / Wheat Flour (மைதா மாவு)');
        $this->assertNotNull($maida);
        $this->assertSame('flour', $maida->category);
        $this->assertSame('gm', $maida->unit);
        $this->assertGreaterThan(0, (float) $maida->current_stock);

        $oil = $ingredients->firstWhere('name', 'Gold Winner Oil');
        $this->assertNotNull($oil);
        $this->assertSame('oil', $oil->category);
        $this->assertSame('ml', $oil->unit);

        $cakeBoard = $ingredients->firstWhere('name', 'Cake Board 1 kg (கேக் போர்டு 1 கிலோ)');
        $this->assertNotNull($cakeBoard);
        $this->assertSame('cake_packaging', $cakeBoard->category);
        $this->assertSame('pcs', $cakeBoard->unit);

        $carryBag = $ingredients->firstWhere('name', 'Easy Carry Bag 10 x 13 (ஈஸி கேரி பேக் 10 x 13)');
        $this->assertNotNull($carryBag);
        $this->assertSame('carry_bags', $carryBag->category);

        $cover = $ingredients->firstWhere('name', 'Milk Cover 5 x 7 (பால் கவர் 5 x 7)');
        $this->assertNotNull($cover);
        $this->assertSame('food_packing_covers', $cover->category);

        $products = Product::withoutGlobalScopes()->where('organization_id', $org->id)->get();
        $this->assertCount(count($productDefs), $products);

        $cake = $products->firstWhere('name', 'Chocolate Cake');
        $this->assertNotNull($cake);
        $this->assertSame('bakery', $cake->category);
        $this->assertSame('own', $cake->product_source);
        $this->assertSame(0.0, (float) $cake->current_stock);

        $jam = $products->firstWhere('name', 'Mixed Jam');
        $this->assertNotNull($jam);
        $this->assertSame('bought', $jam->product_source);
        $this->assertSame(0, Recipe::where('product_id', $jam->id)->count());

        $bread = $products->firstWhere('name', 'White Bread');
        $this->assertNotNull($bread);
        $this->assertGreaterThan(0, Recipe::where('product_id', $bread->id)->count());

        $this->assertSame(0, ProductionBatch::withoutGlobalScopes()->where('organization_id', $org->id)->count());
        $this->assertSame(0, DB::table('billings')->where('organization_id', $org->id)->count());
        $this->assertSame(0, DB::table('branch_transfers')->where('organization_id', $org->id)->count());
        $this->assertSame(0, DB::table('material_issues')->where('organization_id', $org->id)->count());
    }

    public function test_new_organization_gets_the_same_catalog_with_zero_stock(): void
    {
        $reg = $this->postJson('/api/v1/Organization/new', [
            'data' => [
                'values' => [
                    'name' => 'New Catalog Bakery',
                    'firstUser' => [
                        'firstName' => 'New',
                        'lastName' => 'Admin',
                        'email' => 'new.admin@example.test',
                        'phoneNumber' => '+919900000099',
                        'password' => 'Demo@12345',
                        'confirmPassword' => 'Demo@12345',
                    ],
                ],
            ],
        ]);

        $reg->assertStatus(201);
        $orgId = $reg->json('data.org_id');
        $this->assertNotEmpty($orgId);

        $ingredientDefs = require base_path('client-pvt/ingredients.php');
        $productDefs = require base_path('client-pvt/products.php');

        $this->assertSame(
            count($ingredientDefs),
            Ingredient::withoutGlobalScopes()->where('organization_id', $orgId)->count()
        );
        $this->assertSame(
            count($productDefs),
            Product::withoutGlobalScopes()->where('organization_id', $orgId)->count()
        );

        $maida = Ingredient::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('name', 'Maida / Wheat Flour (மைதா மாவு)')
            ->first();
        $this->assertNotNull($maida);
        $this->assertSame('flour', $maida->category);
        $this->assertSame(0.0, (float) $maida->current_stock);

        $this->assertSame(0, ProductionBatch::withoutGlobalScopes()->where('organization_id', $orgId)->count());
        $this->assertSame(0, DB::table('billings')->where('organization_id', $orgId)->count());
        $this->assertSame(0, DB::table('inventory_transactions')->where('organization_id', $orgId)->count());
    }

    public function test_catalog_seed_is_idempotent_and_renames_aliases_without_changing_stock_or_unit(): void
    {
        $this->seed(ClientDemoBakerySeeder::class);

        $org = Organization::where('email', 'demo@client-bakery.test')->first();
        $this->assertNotNull($org);

        $ghee = Ingredient::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('name', 'Ghee (நெய்)')
            ->first();
        $this->assertNotNull($ghee);

        // Simulate a legacy row name + custom stock/unit that must be preserved on re-seed.
        $ghee->name = 'Ghee';
        $ghee->current_stock = 12345;
        $ghee->unit = 'gm';
        $ghee->save();

        $legacyId = $ghee->id;
        $countBefore = Ingredient::withoutGlobalScopes()->where('organization_id', $org->id)->count();

        app(DefaultCatalogService::class)->seedForOrganization((string) $org->id, false);
        app(DefaultCatalogService::class)->seedForOrganization((string) $org->id, false);

        $countAfter = Ingredient::withoutGlobalScopes()->where('organization_id', $org->id)->count();
        $this->assertSame($countBefore, $countAfter);

        $renamed = Ingredient::withoutGlobalScopes()->find($legacyId);
        $this->assertNotNull($renamed);
        $this->assertSame('Ghee (நெய்)', $renamed->name);
        $this->assertSame(12345.0, (float) $renamed->current_stock);
        $this->assertSame('gm', $renamed->unit);

        $ingredientDefs = require base_path('client-pvt/ingredients.php');
        $this->assertSame(
            count($ingredientDefs),
            Ingredient::withoutGlobalScopes()->where('organization_id', $org->id)->count()
        );

        $names = Ingredient::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->pluck('name');
        $this->assertSame($names->count(), $names->unique()->count());
    }

    public function test_packaging_categories_are_accepted_by_model(): void
    {
        foreach (['cake_packaging', 'food_packing_covers', 'carry_bags'] as $category) {
            $this->assertContains($category, Ingredient::CATEGORIES);
        }
    }
}
