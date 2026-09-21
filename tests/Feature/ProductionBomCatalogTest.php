<?php

namespace Tests\Feature;

use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\Recipe\Models\Recipe;
use App\Modules\Api\V1\User\Models\User;
use App\Services\DefaultCatalogService;
use Database\Seeders\ClientDemoBakerySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductionBomCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function loginDemoAdmin(): User
    {
        $this->seed(ClientDemoBakerySeeder::class);
        $org = Organization::where('email', 'demo@client-bakery.test')->firstOrFail();
        $user = User::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('email', 'demo.admin@client-bakery.test')
            ->first()
            ?? User::withoutGlobalScopes()->where('organization_id', $org->id)->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_catalog_seeds_pending_recipe_lines_and_new_production_products(): void
    {
        $this->seed(ClientDemoBakerySeeder::class);
        $org = Organization::where('email', 'demo@client-bakery.test')->firstOrFail();

        $chocolate = Product::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('name', 'Chocolate Cake')
            ->first();
        $this->assertNotNull($chocolate);

        $pending = Recipe::where('product_id', $chocolate->id)
            ->where('quantity_pending', true)
            ->count();
        $this->assertGreaterThan(0, $pending);
        $this->assertSame('incomplete', Recipe::statusForRecipes(Recipe::where('product_id', $chocolate->id)->get()));

        $vanilla = Product::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('name', 'Vanilla Cake')
            ->first();
        $this->assertNotNull($vanilla);
        $this->assertSame('complete', Recipe::statusForRecipes(Recipe::where('product_id', $vanilla->id)->get()));

        $this->assertNotNull(
            Product::withoutGlobalScopes()->where('organization_id', $org->id)->where('name', 'Veg Puff')->first()
        );
        $this->assertNotNull(
            Ingredient::withoutGlobalScopes()->where('organization_id', $org->id)->where('name', 'Caramilk')->first()
        );
        $this->assertNotNull(
            Ingredient::withoutGlobalScopes()->where('organization_id', $org->id)->where('name', 'Egg')->first()
        );
    }

    public function test_production_batch_rejects_incomplete_recipe(): void
    {
        $user = $this->loginDemoAdmin();
        $orgId = $user->organization_id;

        $product = Product::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('name', 'Chocolate Cake')
            ->firstOrFail();

        $res = $this->postJson('/api/v1/ProductionBatch/new', [
            'data' => [
                'values' => [
                    'productId' => $product->id,
                    'quantityProduced' => 1000,
                    'productionDate' => now()->toDateTimeString(),
                ],
            ],
        ], [
            'Idempotency-Key' => 'test-incomplete-bom-'.uniqid(),
        ]);

        $res->assertStatus(400);
        $this->assertStringContainsString('incomplete', strtolower($res->json('message') ?? ''));
    }

    public function test_production_batch_allows_complete_vanilla_cake_recipe(): void
    {
        $user = $this->loginDemoAdmin();
        $orgId = $user->organization_id;

        $product = Product::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('name', 'Vanilla Cake')
            ->firstOrFail();

        $res = $this->postJson('/api/v1/ProductionBatch/new', [
            'data' => [
                'values' => [
                    'productId' => $product->id,
                    'quantityProduced' => 1000,
                    'productionDate' => now()->toDateTimeString(),
                ],
            ],
        ], [
            'Idempotency-Key' => 'test-complete-bom-'.uniqid(),
        ]);

        $res->assertStatus(201);
    }

    public function test_seed_is_idempotent_for_production_boms(): void
    {
        $this->seed(ClientDemoBakerySeeder::class);
        $org = Organization::where('email', 'demo@client-bakery.test')->firstOrFail();
        $beforeProducts = Product::withoutGlobalScopes()->where('organization_id', $org->id)->count();
        $beforeRecipes = Recipe::count();

        app(DefaultCatalogService::class)->seedForOrganization((string) $org->id, false);
        app(DefaultCatalogService::class)->seedForOrganization((string) $org->id, false);

        $this->assertSame(
            $beforeProducts,
            Product::withoutGlobalScopes()->where('organization_id', $org->id)->count()
        );
        $this->assertSame($beforeRecipes, Recipe::count());
    }
}
