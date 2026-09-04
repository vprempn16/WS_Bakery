<?php

namespace Tests\Feature;

use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use App\Modules\Api\V1\InventoryTransaction\Models\InventoryTransaction;
use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\User\Models\User;
use App\Modules\Api\V1\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FilterTest extends TestCase
{
    use RefreshDatabase;

    private $orgA;
    private $orgB;
    private $userA;
    private $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::create(['name' => 'Org A Bakery']);
        $this->orgB = Organization::create(['name' => 'Org B Sweets']);

        $this->userA = User::create([
            'organization_id' => $this->orgA->id,
            'first_name' => 'Arif',
            'last_name' => 'Imran',
            'email' => 'arif@orga.com',
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);

        $this->userB = User::create([
            'organization_id' => $this->orgB->id,
            'first_name' => 'Bob',
            'last_name' => 'Sweets',
            'email' => 'bob@orgb.com',
            'role' => 'staff',
            'password' => Hash::make('password'),
        ]);
    }

    private function makeIngredient(array $attrs): Ingredient
    {
        $ingredient = new Ingredient();
        foreach ($attrs as $key => $value) {
            $ingredient->{$key} = $value;
        }
        $ingredient->save();

        return $ingredient;
    }

    private function makeProduct(array $attrs): Product
    {
        $product = new Product();
        foreach ($attrs as $key => $value) {
            $product->{$key} = $value;
        }
        $product->save();

        return $product;
    }

    public function test_organization_scoping_is_enforced_across_all_endpoints()
    {
        $vendorA = Vendor::create(['organization_id' => $this->orgA->id, 'name' => 'Supplier A']);
        $ingA = $this->makeIngredient([
            'organization_id' => $this->orgA->id,
            'name' => 'Flour',
            'unit' => 'kg',
            'current_stock' => 10,
            'minimum_stock_level' => 5,
        ]);
        $prodA = $this->makeProduct([
            'organization_id' => $this->orgA->id,
            'name' => 'Bread A',
            'price' => 10,
            'unit' => 'pcs',
            'current_stock' => 10,
        ]);
        $txA = InventoryTransaction::create([
            'organization_id' => $this->orgA->id,
            'ingredient_id' => $ingA->id,
            'type' => 'in',
            'quantity' => 10,
        ]);

        Vendor::create(['organization_id' => $this->orgB->id, 'name' => 'Supplier B']);
        $ingB = $this->makeIngredient([
            'organization_id' => $this->orgB->id,
            'name' => 'Sugar',
            'unit' => 'kg',
            'current_stock' => 20,
            'minimum_stock_level' => 5,
        ]);
        $this->makeProduct([
            'organization_id' => $this->orgB->id,
            'name' => 'Cake B',
            'price' => 20,
            'unit' => 'pcs',
            'current_stock' => 5,
        ]);
        InventoryTransaction::create([
            'organization_id' => $this->orgB->id,
            'ingredient_id' => $ingB->id,
            'type' => 'in',
            'quantity' => 20,
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($this->userA);

        $res = $this->getJson('/api/v1/settings/User');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.list'));
        $this->assertEquals($this->userA->id, $res->json('data.list.0.id'));

        $res = $this->getJson('/api/v1/Vendor');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.list'));
        $this->assertEquals($vendorA->id, $res->json('data.list.0.id'));

        $res = $this->getJson('/api/v1/Ingredient');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.list'));
        $this->assertEquals($ingA->id, $res->json('data.list.0.id'));

        $res = $this->getJson('/api/v1/Product');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.list'));
        $this->assertEquals($prodA->id, $res->json('data.list.0.id'));

        $res = $this->getJson('/api/v1/InventoryTransaction');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.list'));
        $this->assertEquals($txA->id, $res->json('data.list.0.id'));
    }

    public function test_user_filters()
    {
        $adminUser = User::create([
            'organization_id' => $this->orgA->id,
            'first_name' => 'Prem',
            'last_name' => 'Nath',
            'email' => 'prem@orga.com',
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($this->userA);

        $res = $this->getJson('/api/v1/settings/User?role=admin');
        $res->assertStatus(200);
        $this->assertCount(2, $res->json('data.list'));
        $ids = collect($res->json('data.list'))->pluck('id')->all();
        $this->assertContains($adminUser->id, $ids);
        $this->assertContains($this->userA->id, $ids);

        $res = $this->getJson('/api/v1/settings/User?search=Prem');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.list'));
        $this->assertEquals($adminUser->id, $res->json('data.list.0.id'));

        $res = $this->getJson('/api/v1/settings/User?search=Nonexistent');
        $res->assertStatus(200);
        $this->assertCount(0, $res->json('data.list'));
    }

    public function test_vendor_filters()
    {
        $vendor1 = Vendor::create(['organization_id' => $this->orgA->id, 'name' => 'Global Flour', 'contact_person' => 'Jane']);
        $vendor2 = Vendor::create(['organization_id' => $this->orgA->id, 'name' => 'Local Sugar', 'contact_person' => 'Jack']);

        \Laravel\Sanctum\Sanctum::actingAs($this->userA);

        $res = $this->getJson('/api/v1/Vendor?search=Global');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.list'));
        $this->assertEquals($vendor1->id, $res->json('data.list.0.id'));

        $res = $this->getJson('/api/v1/Vendor?search=Jack');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.list'));
        $this->assertEquals($vendor2->id, $res->json('data.list.0.id'));
    }

    public function test_ingredient_filters()
    {
        $vendor = Vendor::create(['organization_id' => $this->orgA->id, 'name' => 'Sugar Supplier']);

        $ing1 = $this->makeIngredient([
            'organization_id' => $this->orgA->id,
            'vendor_id' => $vendor->id,
            'name' => 'Fine Sugar',
            'unit' => 'g',
            'current_stock' => 100,
            'minimum_stock_level' => 500,
        ]);

        $ing2 = $this->makeIngredient([
            'organization_id' => $this->orgA->id,
            'name' => 'Wheat Flour',
            'unit' => 'kg',
            'current_stock' => 10,
            'minimum_stock_level' => 2,
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($this->userA);

        $res = $this->getJson('/api/v1/Ingredient?search=Wheat');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.list'));
        $this->assertEquals($ing2->id, $res->json('data.list.0.id'));

        $res = $this->getJson('/api/v1/Ingredient?vendorId=' . $vendor->id);
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.list'));
        $this->assertEquals($ing1->id, $res->json('data.list.0.id'));

        $res = $this->getJson('/api/v1/Ingredient?stockStatus=low');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.list'));
        $this->assertEquals($ing1->id, $res->json('data.list.0.id'));

        $res = $this->getJson('/api/v1/Ingredient?stockStatus=in_stock');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.list'));
        $this->assertEquals($ing2->id, $res->json('data.list.0.id'));
    }

    public function test_inventory_transaction_filters()
    {
        $ing = $this->makeIngredient([
            'organization_id' => $this->orgA->id,
            'name' => 'Yeast',
            'unit' => 'pkt',
        ]);

        $tx1 = new InventoryTransaction([
            'organization_id' => $this->orgA->id,
            'ingredient_id' => $ing->id,
            'type' => 'in',
            'quantity' => 10,
        ]);
        $tx1->created_at = '2026-06-01 10:00:00';
        $tx1->save();

        $tx2 = new InventoryTransaction([
            'organization_id' => $this->orgA->id,
            'ingredient_id' => $ing->id,
            'type' => 'waste',
            'quantity' => 2,
        ]);
        $tx2->created_at = '2026-06-10 12:00:00';
        $tx2->save();

        \Laravel\Sanctum\Sanctum::actingAs($this->userA);

        $res = $this->getJson('/api/v1/InventoryTransaction?ingredientId=' . $ing->id);
        $res->assertStatus(200);
        $this->assertCount(2, $res->json('data.list'));

        $res = $this->getJson('/api/v1/InventoryTransaction?type=waste');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.list'));
        $this->assertEquals($tx2->id, $res->json('data.list.0.id'));

        $res = $this->getJson('/api/v1/InventoryTransaction?startDate=2026-06-05&endDate=2026-06-15');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.list'));
        $this->assertEquals($tx2->id, $res->json('data.list.0.id'));
    }

    public function test_product_filters()
    {
        $prod1 = $this->makeProduct([
            'organization_id' => $this->orgA->id,
            'name' => 'Sweet Bread',
            'price' => 50,
            'unit' => 'pcs',
            'current_stock' => 0,
        ]);

        $prod2 = $this->makeProduct([
            'organization_id' => $this->orgA->id,
            'name' => 'Fruit Cake',
            'price' => 200,
            'unit' => 'kg',
            'current_stock' => 5,
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($this->userA);

        $res = $this->getJson('/api/v1/Product?search=' . $prod1->product_number);
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.list'));
        $this->assertEquals($prod1->id, $res->json('data.list.0.id'));

        $res = $this->getJson('/api/v1/Product?unit=kg');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.list'));
        $this->assertEquals($prod2->id, $res->json('data.list.0.id'));

        $res = $this->getJson('/api/v1/Product?stockStatus=out_of_stock');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.list'));
        $this->assertEquals($prod1->id, $res->json('data.list.0.id'));

        $res = $this->getJson('/api/v1/Product?stockStatus=in_stock');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.list'));
        $this->assertEquals($prod2->id, $res->json('data.list.0.id'));
    }

    public function test_saved_filters_can_be_created_listed_and_deleted()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->userA);

        $rules = [
            'logical_operator' => 'AND',
            'conditions' => [
                ['field' => 'price', 'operator' => '>', 'value' => 100],
            ],
        ];

        $res = $this->postJson('/api/v1/filters/new', [
            'data' => [
                'values' => [
                    'name' => 'High Price Products',
                    'module' => 'Product',
                    'isPublic' => true,
                    'rules' => $rules,
                    'headerDetails' => [
                        ['fieldname' => 'name', 'fieldlabel' => 'Name'],
                        ['fieldname' => 'price', 'fieldlabel' => 'Price'],
                    ],
                ],
            ],
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'High Price Products',
                    'module' => 'products',
                    'isPublic' => true,
                    'rules' => $rules,
                ],
            ]);

        $filterId = $res->json('data.id');

        $res = $this->getJson('/api/v1/filters?module=Product');
        $res->assertStatus(200);
        $filterIds = collect($res->json('data'))->pluck('id')->toArray();
        $this->assertContains($filterId, $filterIds);

        $res = $this->deleteJson('/api/v1/filters/' . $filterId);
        $res->assertStatus(200);

        $this->assertDatabaseMissing('saved-filters', ['id' => $filterId]);
    }

    public function test_saved_filters_scoping_is_enforced()
    {
        $filterB = \App\Modules\Api\V1\SavedFilter\Models\SavedFilter::create([
            'organization_id' => $this->orgB->id,
            'user_id' => $this->userB->id,
            'name' => 'Org B Filter',
            'module' => 'products',
            'rules' => ['conditions' => []],
            'is_public' => true,
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($this->userA);

        $res = $this->getJson('/api/v1/filters?module=Product');
        $res->assertStatus(200);
        $filterIds = collect($res->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($filterB->id, $filterIds);

        $res = $this->deleteJson('/api/v1/filters/' . $filterB->id);
        $res->assertStatus(404);
    }

    public function test_can_apply_saved_filters_to_listing()
    {
        $prod1 = $this->makeProduct([
            'organization_id' => $this->orgA->id,
            'name' => 'Cake A',
            'price' => 200,
            'unit' => 'pcs',
        ]);

        $this->makeProduct([
            'organization_id' => $this->orgA->id,
            'name' => 'Bread A',
            'price' => 30,
            'unit' => 'pcs',
        ]);

        $filter = \App\Modules\Api\V1\SavedFilter\Models\SavedFilter::create([
            'organization_id' => $this->orgA->id,
            'user_id' => $this->userA->id,
            'name' => 'Expensive',
            'module' => 'products',
            'rules' => [
                'logical_operator' => 'AND',
                'conditions' => [
                    ['field' => 'price', 'operator' => '>', 'value' => 100],
                ],
            ],
            'is_public' => false,
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($this->userA);

        $res = $this->getJson('/api/v1/Product?savedFilterId=' . $filter->id);
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.list'));
        $this->assertEquals($prod1->id, $res->json('data.list.0.id'));
    }

    public function test_can_apply_dynamic_rules_to_listing()
    {
        $this->makeProduct([
            'organization_id' => $this->orgA->id,
            'name' => 'Cake A',
            'price' => 200,
            'unit' => 'pcs',
        ]);

        $prod2 = $this->makeProduct([
            'organization_id' => $this->orgA->id,
            'name' => 'Bread A',
            'price' => 30,
            'unit' => 'pcs',
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($this->userA);

        $rules = [
            'logical_operator' => 'AND',
            'conditions' => [
                ['field' => 'price', 'operator' => '<', 'value' => 100],
            ],
        ];

        $res = $this->getJson('/api/v1/Product?' . http_build_query(['rules' => $rules]));
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.list'));
        $this->assertEquals($prod2->id, $res->json('data.list.0.id'));
    }

    public function test_unwhitelisted_fields_throw_validation_error()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->userA);

        $rules = [
            'logical_operator' => 'AND',
            'conditions' => [
                ['field' => 'password', 'operator' => '=', 'value' => 'leak'],
            ],
        ];

        $res = $this->getJson('/api/v1/Product?' . http_build_query(['rules' => $rules]));

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['rules']);
    }

    public function test_product_default_headers_include_all_displaytype_1_and_3_fields()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->userA);

        \App\Modules\Api\V1\SavedFilter\Models\SavedFilter::create([
            'organization_id' => null,
            'user_id' => null,
            'name' => 'All',
            'module' => 'products',
            'rules' => [],
            'is_public' => true,
            'is_default' => true,
            'header_details' => [
                ['fieldname' => 'name', 'fieldlabel' => 'Name'],
                ['fieldname' => 'shelfLifeDays', 'fieldlabel' => 'Shelf Life Days'],
                ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At'],
            ],
        ]);

        $res = $this->getJson('/api/v1/Product/headers/default');
        $res->assertStatus(200);
        $this->assertTrue($res->json('data.is_default'));

        $names = collect($res->json('data.fields'))->pluck('fieldname')->all();
        $this->assertContains('shelfLife', $names);
        $this->assertContains('productImage', $names);
        $this->assertContains('currentStock', $names);
        $this->assertNotContains('createdAt', $names);
        $this->assertNotContains('updatedAt', $names);
        $this->assertNotContains('shelfLifeDays', $names);

        $defaultId = $res->json('data.filter_id');
        $this->assertNotEmpty($defaultId);

        $resById = $this->getJson('/api/v1/Product/headers/' . $defaultId);
        $resById->assertStatus(200);
        $namesById = collect($resById->json('data.fields'))->pluck('fieldname')->all();
        $this->assertContains('shelfLife', $namesById);
        $this->assertContains('productImage', $namesById);
        $this->assertNotContains('createdAt', $namesById);
    }

    public function test_custom_filter_headers_respect_header_details()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->userA);

        $filter = \App\Modules\Api\V1\SavedFilter\Models\SavedFilter::create([
            'organization_id' => $this->orgA->id,
            'user_id' => $this->userA->id,
            'name' => 'Minimal',
            'module' => 'products',
            'rules' => ['logical_operator' => 'AND', 'conditions' => []],
            'is_public' => false,
            'is_default' => false,
            'header_details' => [
                ['fieldname' => 'name', 'fieldlabel' => 'Name'],
                ['fieldname' => 'price', 'fieldlabel' => 'Price'],
            ],
        ]);

        $res = $this->getJson('/api/v1/Product/headers/' . $filter->id);
        $res->assertStatus(200);
        $names = collect($res->json('data.fields'))->pluck('fieldname')->all();
        $this->assertEquals(['name', 'price'], $names);
    }

    public function test_column_only_saved_filter_can_be_created()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->userA);

        $res = $this->postJson('/api/v1/filters/new', [
            'data' => [
                'values' => [
                    'name' => 'Name And Image',
                    'module' => 'Product',
                    'isPublic' => false,
                    'rules' => [
                        'logical_operator' => 'AND',
                        'conditions' => [],
                    ],
                    'headerDetails' => [
                        ['fieldname' => 'name', 'fieldlabel' => 'Name'],
                        ['fieldname' => 'productImage', 'fieldlabel' => 'Product Image'],
                    ],
                ],
            ],
        ]);

        $res->assertStatus(201);
        $this->assertEquals('Name And Image', $res->json('data.name'));
        $this->assertEquals([], $res->json('data.rules.conditions'));
        $this->assertCount(2, $res->json('data.headerDetails'));
    }

    public function test_custom_saved_filter_can_be_updated()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->userA);

        $filter = \App\Modules\Api\V1\SavedFilter\Models\SavedFilter::create([
            'organization_id' => $this->orgA->id,
            'user_id' => $this->userA->id,
            'name' => 'Minimal',
            'module' => 'products',
            'rules' => ['logical_operator' => 'AND', 'conditions' => []],
            'is_public' => false,
            'is_default' => false,
            'header_details' => [
                ['fieldname' => 'name', 'fieldlabel' => 'Name'],
            ],
        ]);

        $res = $this->postJson('/api/v1/filters/' . $filter->id, [
            'data' => [
                'values' => [
                    'name' => 'Name And Price',
                    'headerDetails' => [
                        ['fieldname' => 'name', 'fieldlabel' => 'Name'],
                        ['fieldname' => 'price', 'fieldlabel' => 'Price'],
                    ],
                ],
            ],
        ]);

        $res->assertStatus(200);
        $this->assertEquals('Name And Price', $res->json('data.name'));
        $this->assertCount(2, $res->json('data.headerDetails'));

        $headers = $this->getJson('/api/v1/Product/headers/' . $filter->id);
        $headers->assertStatus(200);
        $this->assertEquals(
            ['name', 'price'],
            collect($headers->json('data.fields'))->pluck('fieldname')->all()
        );
    }

    public function test_default_saved_filter_cannot_be_updated_or_deleted()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->userA);

        $default = \App\Modules\Api\V1\SavedFilter\Models\SavedFilter::create([
            'organization_id' => $this->orgA->id,
            'user_id' => $this->userA->id,
            'name' => 'All',
            'module' => 'products',
            'rules' => [],
            'is_public' => true,
            'is_default' => true,
            'header_details' => [
                ['fieldname' => 'name', 'fieldlabel' => 'Name'],
            ],
        ]);

        $update = $this->postJson('/api/v1/filters/' . $default->id, [
            'data' => [
                'values' => [
                    'name' => 'Hacked',
                    'headerDetails' => [
                        ['fieldname' => 'name', 'fieldlabel' => 'Name'],
                    ],
                ],
            ],
        ]);
        $update->assertStatus(403);

        $delete = $this->deleteJson('/api/v1/filters/' . $default->id);
        $delete->assertStatus(403);
    }
}
