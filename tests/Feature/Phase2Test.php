<?php

namespace Tests\Feature;

use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\Vendor\Models\Vendor;
use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use App\Modules\Api\V1\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase2Test extends TestCase
{
    use RefreshDatabase;

    public function test_can_manage_phase_2_flow()
    {
        // 1. Create Organization & User
        $org = Organization::create(['name' => 'WS Bakery']);
        $user = \App\Modules\Api\V1\User\Models\User::create([
            'organization_id' => $org->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'testuser@example.com',
            'role' => 'admin',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($user);

        // 2. Vendor
        $vendorResponse = $this->postJson('/api/v1/vendors/new', [
            'data' => [
                'values' => [
                    'organizationId' => $org->id,
                    'name' => 'Supplier A',
                ]
            ]
        ]);
        $vendorResponse->assertStatus(201);
        $vendorId = $vendorResponse->json('data.values.id');

        // 3. Ingredient
        $ingredientResponse = $this->postJson('/api/v1/ingredients/new', [
            'data' => [
                'values' => [
                    'organizationId' => $org->id,
                    'vendorId' => $vendorId,
                    'name' => 'Sugar',
                    'unit' => 'g',
                ]
            ]
        ]);
        $ingredientResponse->assertStatus(201);
        $ingredientId = $ingredientResponse->json('data.values.id');

        // 4. Inventory Transaction (Add Stock)
        $txResponse = $this->postJson('/api/v1/inventory-transactions/new', [
            'data' => [
                'values' => [
                    'organizationId' => $org->id,
                    'ingredientId' => $ingredientId,
                    'type' => 'in',
                    'quantity' => 1000,
                    'referenceNote' => 'Purchased 1kg Sugar'
                ]
            ]
        ]);
        $txResponse->assertStatus(201);

        // Verify ingredient stock increased to 1000
        $this->assertDatabaseHas('ingredients', [
            'id' => $ingredientId,
            'current_stock' => 1000,
        ]);

        // 5. Product
        $productResponse = $this->postJson('/api/v1/products/new', [
            'data' => [
                'values' => [
                    'organizationId' => $org->id,
                    'name' => 'Sweet Bread',
                    'price' => 50,
                ]
            ]
        ]);
        $productResponse->assertStatus(201);
        $productId = $productResponse->json('data.values.id');

        // 6. Recipe
        $recipeResponse = $this->postJson("/api/v1/products/{$productId}/recipe/new", [
            'data' => [
                'values' => [
                    'ingredientId' => $ingredientId,
                    'quantityRequired' => 200, // 200g of sugar per bread
                ]
            ]
        ]);
        $recipeResponse->assertStatus(201);

        // Verify Recipe
        $this->assertDatabaseHas('recipes', [
            'product_id' => $productId,
            'ingredient_id' => $ingredientId,
            'quantity_required' => 200,
        ]);
    }
}
