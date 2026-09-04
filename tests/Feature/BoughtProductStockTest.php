<?php

namespace Tests\Feature;

use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\Recipe\Models\Recipe;
use App\Modules\Api\V1\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BoughtProductStockTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private Product $bought;
    private Product $own;
    private Ingredient $ingredient;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->org = Organization::create(['name' => 'Bought Flow Bakery']);
        $this->admin = User::create([
            'organization_id' => $this->org->id,
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'bought-stock@example.com',
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);

        $this->bought = new Product();
        $this->bought->organization_id = $this->org->id;
        $this->bought->name = 'Brand Biscuit';
        $this->bought->price = 20;
        $this->bought->unit = 'pcs';
        $this->bought->category = 'biscuit';
        $this->bought->product_source = 'bought';
        $this->bought->status = 'active';
        $this->bought->current_stock = 0;
        $this->bought->save();

        $this->own = new Product();
        $this->own->organization_id = $this->org->id;
        $this->own->name = 'Butter Bun';
        $this->own->price = 40;
        $this->own->unit = 'pcs';
        $this->own->category = 'bread';
        $this->own->product_source = 'own';
        $this->own->status = 'active';
        $this->own->current_stock = 0;
        $this->own->save();

        $this->ingredient = new Ingredient();
        $this->ingredient->organization_id = $this->org->id;
        $this->ingredient->name = 'Flour';
        $this->ingredient->unit = 'gm';
        $this->ingredient->category = 'raw';
        $this->ingredient->minimum_stock_level = 0;
        $this->ingredient->current_stock = 1000;
        $this->ingredient->save();

        Sanctum::actingAs($this->admin);
    }

    public function test_bought_product_cannot_add_recipe(): void
    {
        $response = $this->postJson("/api/v1/Product/{$this->bought->id}/recipe/new", [
            'data' => [
                'values' => [
                    'ingredientId' => $this->ingredient->id,
                    'quantityRequired' => 10,
                ],
            ],
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('bought', strtolower($response->json('message') ?? ''));
    }

    public function test_bought_product_cannot_create_production_batch(): void
    {
        $response = $this->postJson('/api/v1/ProductionBatch/new', [
            'data' => [
                'values' => [
                    'productId' => $this->bought->id,
                    'quantityProduced' => 5,
                    'productionDate' => now()->format('Y-m-d'),
                ],
            ],
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('bought', strtolower($response->json('message') ?? ''));
        $this->assertEquals(0.0, (float) $this->bought->fresh()->current_stock);
    }

    public function test_receive_stock_increases_bought_product_warehouse_stock(): void
    {
        Artisan::call('migrate:module-fields', ['module' => 'ProductStockTransaction']);

        $response = $this->postJson('/api/v1/ProductStockTransaction/new', [
            'data' => [
                'values' => [
                    'productId' => $this->bought->id,
                    'type' => 'in',
                    'quantity' => 24,
                    'referenceNote' => 'Vendor invoice 1',
                ],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertEquals(24.0, (float) $this->bought->fresh()->current_stock);
        $this->assertDatabaseHas('product_stock_transactions', [
            'product_id' => $this->bought->id,
            'type' => 'in',
            'quantity' => 24,
        ]);
    }

    public function test_own_product_cannot_receive_stock_via_product_stock_transaction(): void
    {
        $response = $this->postJson('/api/v1/ProductStockTransaction/new', [
            'data' => [
                'values' => [
                    'productId' => $this->own->id,
                    'type' => 'in',
                    'quantity' => 10,
                ],
            ],
        ]);

        $response->assertStatus(400);
        $this->assertEquals(0.0, (float) $this->own->fresh()->current_stock);
    }

    public function test_ingredient_defaults_to_raw_category(): void
    {
        $ing = new Ingredient();
        $ing->organization_id = $this->org->id;
        $ing->name = 'Paper Cup';
        $ing->unit = 'pcs';
        $ing->current_stock = 0;
        $ing->save();

        $this->assertEquals('raw', $ing->fresh()->category);

        $ing->category = 'packaging';
        $ing->save();
        $this->assertEquals('packaging', $ing->fresh()->category);
    }

    public function test_product_list_filters_by_category_and_source(): void
    {
        $response = $this->getJson('/api/v1/Product?category=biscuit&productSource=bought');
        $response->assertStatus(200);
        $list = $response->json('data.list') ?? $response->json('data.records') ?? $response->json('data.data.list') ?? [];
        $ids = collect($list)->map(fn ($row) => $row['id'] ?? $row['values']['id'] ?? null)->filter()->values();
        $this->assertTrue($ids->contains($this->bought->id));
        $this->assertFalse($ids->contains($this->own->id));
    }

    public function test_product_create_form_defaults_include_product_source(): void
    {
        Artisan::call('migrate:module-fields', ['module' => 'Product']);
        $response = $this->getJson('/api/v1/Product/new');
        $response->assertStatus(200);
        $response->assertJsonPath('data.values.productSource', 'own');
    }

    public function test_own_product_with_recipe_can_still_produce(): void
    {
        Recipe::create([
            'product_id' => $this->own->id,
            'ingredient_id' => $this->ingredient->id,
            'quantity_required' => 50,
        ]);

        $response = $this->postJson('/api/v1/ProductionBatch/new', [
            'data' => [
                'values' => [
                    'productId' => $this->own->id,
                    'quantityProduced' => 2,
                    'productionDate' => now()->format('Y-m-d'),
                ],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertEquals(2.0, (float) $this->own->fresh()->current_stock);
    }
}
