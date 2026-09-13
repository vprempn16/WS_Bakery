<?php

namespace Tests\Feature;

use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\ProductionBatch\Models\ProductionBatch;
use App\Modules\Api\V1\Recipe\Models\Recipe;
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

        $maida = $ingredients->firstWhere('name', 'Maida Flour');
        $this->assertNotNull($maida);
        $this->assertSame('flour', $maida->category);
        $this->assertSame('gm', $maida->unit);
        $this->assertGreaterThan(0, (float) $maida->current_stock);

        $oil = $ingredients->firstWhere('name', 'Gold Winner Oil');
        $this->assertNotNull($oil);
        $this->assertSame('oil', $oil->category);
        $this->assertSame('ml', $oil->unit);

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
            ->where('name', 'Maida Flour')
            ->first();
        $this->assertNotNull($maida);
        $this->assertSame('flour', $maida->category);
        $this->assertSame(0.0, (float) $maida->current_stock);

        $this->assertSame(0, ProductionBatch::withoutGlobalScopes()->where('organization_id', $orgId)->count());
        $this->assertSame(0, DB::table('billings')->where('organization_id', $orgId)->count());
        $this->assertSame(0, DB::table('inventory_transactions')->where('organization_id', $orgId)->count());
    }
}
