<?php

namespace Tests\Feature;

use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\ProductionBatch\Models\ProductionBatch;
use App\Modules\Api\V1\Recipe\Models\Recipe;
use App\Modules\Api\V1\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BakeryFixtures;
use Tests\TestCase;

class ProductionBatchIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private Ingredient $ingredient;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->org = Organization::create(['name' => 'Idempotent Bakery']);
        $this->admin = User::create([
            'organization_id' => $this->org->id,
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin-produce-idem@example.com',
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);

        $this->ingredient = new Ingredient();
        $this->ingredient->organization_id = $this->org->id;
        $this->ingredient->name = 'Flour';
        $this->ingredient->unit = 'gm';
        $this->ingredient->minimum_stock_level = 0;
        $this->ingredient->current_stock = 1000;
        $this->ingredient->save();

        Sanctum::actingAs($this->admin);
    }

    private function productWithRecipe(float $stock = 0)
    {
        $product = BakeryFixtures::ownProduct((string) $this->org->id, [
            'current_stock' => $stock,
        ]);

        Recipe::create([
            'product_id' => $product->id,
            'ingredient_id' => $this->ingredient->id,
            'quantity_required' => 10,
        ]);

        return $product;
    }

    private function producePayload($product, float $qty = 2): array
    {
        return [
            'data' => [
                'values' => [
                    'productId' => $product->id,
                    'quantityProduced' => $qty,
                    'productionDate' => now()->toDateTimeString(),
                ],
            ],
        ];
    }

    public function test_missing_idempotency_key_returns_422_and_creates_nothing(): void
    {
        $product = $this->productWithRecipe();

        $this->postJson('/api/v1/ProductionBatch/new', $this->producePayload($product))
            ->assertStatus(422)
            ->assertJsonPath('status', false);

        $this->assertSame(0, ProductionBatch::query()->count());
        $this->assertEquals(0.0, (float) $product->fresh()->current_stock);
    }

    public function test_same_key_twice_creates_one_batch_and_increments_stock_once(): void
    {
        $product = $this->productWithRecipe();
        $headers = ['Idempotency-Key' => 'produce-replay-1'];

        $first = $this->postJson('/api/v1/ProductionBatch/new', $this->producePayload($product, 5), $headers);
        $first->assertStatus(201);
        $id = $first->json('data.id');
        $this->assertNotEmpty($id);

        $second = $this->postJson('/api/v1/ProductionBatch/new', $this->producePayload($product, 5), $headers);
        $second->assertStatus(201);
        $this->assertSame($id, $second->json('data.id'));

        $this->assertSame(1, ProductionBatch::query()->where('product_id', $product->id)->count());
        $this->assertEquals(5.0, (float) $product->fresh()->current_stock);
    }

    public function test_different_keys_create_two_batches(): void
    {
        $product = $this->productWithRecipe();

        $this->postJson('/api/v1/ProductionBatch/new', $this->producePayload($product, 2), [
            'Idempotency-Key' => 'produce-a',
        ])->assertStatus(201);

        $this->postJson('/api/v1/ProductionBatch/new', $this->producePayload($product, 3), [
            'Idempotency-Key' => 'produce-b',
        ])->assertStatus(201);

        $this->assertSame(2, ProductionBatch::query()->where('product_id', $product->id)->count());
        $this->assertEquals(5.0, (float) $product->fresh()->current_stock);
    }

    public function test_failed_create_does_not_consume_key_so_retry_after_recipe_succeeds(): void
    {
        $product = BakeryFixtures::ownProduct((string) $this->org->id);
        $headers = ['Idempotency-Key' => 'produce-after-fix'];

        $fail = $this->postJson('/api/v1/ProductionBatch/new', $this->producePayload($product, 2), $headers);
        $fail->assertStatus(400);
        $this->assertStringContainsString('recipe', strtolower($fail->json('message') ?? ''));
        $this->assertSame(0, ProductionBatch::query()->count());

        Recipe::create([
            'product_id' => $product->id,
            'ingredient_id' => $this->ingredient->id,
            'quantity_required' => 10,
        ]);

        $ok = $this->postJson('/api/v1/ProductionBatch/new', $this->producePayload($product, 2), $headers);
        $ok->assertStatus(201);
        $this->assertSame(1, ProductionBatch::query()->where('product_id', $product->id)->count());
        $this->assertEquals(2.0, (float) $product->fresh()->current_stock);
    }

    public function test_concurrent_same_key_returns_one_batch(): void
    {
        $product = $this->productWithRecipe();
        $headers = ['Idempotency-Key' => 'produce-lock-1'];
        $payload = $this->producePayload($product, 4);

        $first = $this->postJson('/api/v1/ProductionBatch/new', $payload, $headers);
        $first->assertStatus(201);

        $second = $this->postJson('/api/v1/ProductionBatch/new', $payload, $headers);
        $second->assertStatus(201);
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, ProductionBatch::query()->where('product_id', $product->id)->count());
        $this->assertEquals(4.0, (float) $product->fresh()->current_stock);
    }
}
