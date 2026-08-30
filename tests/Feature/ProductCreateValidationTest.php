<?php

namespace Tests\Feature;

use App\Modules\Api\V1\Organization\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductCreateValidationTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): Organization
    {
        $org = Organization::create(['name' => 'WS Bakery']);
        $user = \App\Modules\Api\V1\User\Models\User::create([
            'organization_id' => $org->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'product-create-test@example.com',
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);

        Sanctum::actingAs($user);

        return $org;
    }

    public function test_product_create_with_explicit_active_status_succeeds_when_module_fields_seeded(): void
    {
        $org = $this->actingAsAdmin();
        Artisan::call('migrate:module-fields', ['module' => 'Product']);

        $response = $this->postJson('/api/v1/Product/new', [
            'data' => [
                'values' => [
                    'organizationId' => $org->id,
                    'name' => 'Butter Bun',
                    'price' => 40,
                    'unit' => 'pcs',
                    'status' => 'active',
                ],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('products', [
            'id' => $response->json('data.id'),
            'name' => 'Butter Bun',
            'status' => 'active',
        ]);
    }

    public function test_product_create_without_status_uses_model_default_when_module_fields_seeded(): void
    {
        $org = $this->actingAsAdmin();
        Artisan::call('migrate:module-fields', ['module' => 'Product']);

        $response = $this->postJson('/api/v1/Product/new', [
            'data' => [
                'values' => [
                    'organizationId' => $org->id,
                    'name' => 'Plain Bread',
                    'price' => 30,
                    'unit' => 'pcs',
                ],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('products', [
            'id' => $response->json('data.id'),
            'status' => 'active',
        ]);
    }

    public function test_product_create_form_returns_default_status(): void
    {
        $this->actingAsAdmin();
        Artisan::call('migrate:module-fields', ['module' => 'Product']);

        $response = $this->getJson('/api/v1/Product/new');

        $response->assertStatus(200);
        $response->assertJsonPath('data.values.status', 'active');
    }
}
