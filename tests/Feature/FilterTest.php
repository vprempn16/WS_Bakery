<?php

namespace Tests\Feature;

use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\User\Models\User;
use App\Modules\Api\V1\Vendor\Models\Vendor;
use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use App\Modules\Api\V1\InventoryTransaction\Models\InventoryTransaction;
use App\Modules\Api\V1\Product\Models\Product;
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

        // Create Org A and Org B
        $this->orgA = Organization::create(['name' => 'Org A Bakery']);
        $this->orgB = Organization::create(['name' => 'Org B Sweets']);

        // Create Users
        $this->userA = User::create([
            'organization_id' => $this->orgA->id,
            'first_name' => 'Arif',
            'last_name' => 'Imran',
            'email' => 'arif@orga.com',
            'role' => 'owner',
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

    public function test_organization_scoping_is_enforced_across_all_endpoints()
    {
        // Set up data for Org A
        $vendorA = Vendor::create(['organization_id' => $this->orgA->id, 'name' => 'Supplier A']);
        $ingA = Ingredient::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Flour',
            'unit' => 'kg',
            'current_stock' => 10,
            'minimum_stock_level' => 5
        ]);
        $prodA = Product::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Bread A',
            'price' => 10,
            'unit' => 'pcs',
            'current_stock' => 10
        ]);
        $txA = InventoryTransaction::create([
            'organization_id' => $this->orgA->id,
            'ingredient_id' => $ingA->id,
            'type' => 'in',
            'quantity' => 10
        ]);

        // Set up data for Org B
        $vendorB = Vendor::create(['organization_id' => $this->orgB->id, 'name' => 'Supplier B']);
        $ingB = Ingredient::create([
            'organization_id' => $this->orgB->id,
            'name' => 'Sugar',
            'unit' => 'kg',
            'current_stock' => 20,
            'minimum_stock_level' => 5
        ]);
        $prodB = Product::create([
            'organization_id' => $this->orgB->id,
            'name' => 'Cake B',
            'price' => 20,
            'unit' => 'pcs',
            'current_stock' => 5
        ]);
        $txB = InventoryTransaction::create([
            'organization_id' => $this->orgB->id,
            'ingredient_id' => $ingB->id,
            'type' => 'in',
            'quantity' => 20
        ]);

        // Authenticate as User A
        \Laravel\Sanctum\Sanctum::actingAs($this->userA);

        // 1. Users List (Settings)
        $res = $this->getJson('/api/v1/settings/User');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals($this->userA->id, $res->json('data.0.values.id'));

        // 2. Vendors List
        $res = $this->getJson('/api/v1/vendors');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals($vendorA->id, $res->json('data.0.values.id'));

        // 3. Ingredients List
        $res = $this->getJson('/api/v1/ingredients');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals($ingA->id, $res->json('data.0.values.id'));

        // 4. Products List
        $res = $this->getJson('/api/v1/products');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals($prodA->id, $res->json('data.0.values.id'));

        // 5. Inventory Transactions List
        $res = $this->getJson('/api/v1/inventory-transactions');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals($txA->id, $res->json('data.0.values.id'));
    }

    public function test_user_filters()
    {
        // Create an admin user in Org A
        $adminUser = User::create([
            'organization_id' => $this->orgA->id,
            'first_name' => 'Prem',
            'last_name' => 'Nath',
            'email' => 'prem@orga.com',
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($this->userA);

        // Filter by role=admin
        $res = $this->getJson('/api/v1/settings/User?role=admin');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals($adminUser->id, $res->json('data.0.values.id'));

        // Search by keyword "Prem"
        $res = $this->getJson('/api/v1/settings/User?search=Prem');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals($adminUser->id, $res->json('data.0.values.id'));

        // Search that matches nothing
        $res = $this->getJson('/api/v1/settings/User?search=Nonexistent');
        $res->assertStatus(200);
        $this->assertCount(0, $res->json('data'));
    }

    public function test_vendor_filters()
    {
        $vendor1 = Vendor::create(['organization_id' => $this->orgA->id, 'name' => 'Global Flour', 'contact_person' => 'Jane']);
        $vendor2 = Vendor::create(['organization_id' => $this->orgA->id, 'name' => 'Local Sugar', 'contact_person' => 'Jack']);

        \Laravel\Sanctum\Sanctum::actingAs($this->userA);

        // Search by name "Global"
        $res = $this->getJson('/api/v1/vendors?search=Global');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals($vendor1->id, $res->json('data.0.values.id'));

        // Search by contact person "Jack"
        $res = $this->getJson('/api/v1/vendors?search=Jack');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals($vendor2->id, $res->json('data.0.values.id'));
    }

    public function test_ingredient_filters()
    {
        $vendor = Vendor::create(['organization_id' => $this->orgA->id, 'name' => 'Sugar Supplier']);

        $ing1 = Ingredient::create([
            'organization_id' => $this->orgA->id,
            'vendor_id' => $vendor->id,
            'name' => 'Fine Sugar',
            'unit' => 'g',
            'current_stock' => 100,
            'minimum_stock_level' => 500 // Low stock!
        ]);

        $ing2 = Ingredient::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Wheat Flour',
            'unit' => 'kg',
            'current_stock' => 10,
            'minimum_stock_level' => 2 // In stock
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($this->userA);

        // 1. Search by name "Wheat"
        $res = $this->getJson('/api/v1/ingredients?search=Wheat');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals($ing2->id, $res->json('data.0.values.id'));

        // 2. Filter by vendor
        $res = $this->getJson('/api/v1/ingredients?vendorId=' . $vendor->id);
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals($ing1->id, $res->json('data.0.values.id'));

        // 3. Filter by stockStatus=low
        $res = $this->getJson('/api/v1/ingredients?stockStatus=low');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals($ing1->id, $res->json('data.0.values.id'));

        // 4. Filter by stockStatus=in_stock
        $res = $this->getJson('/api/v1/ingredients?stockStatus=in_stock');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals($ing2->id, $res->json('data.0.values.id'));
    }

    public function test_inventory_transaction_filters()
    {
        $ing = Ingredient::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Yeast',
            'unit' => 'pkt'
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

        // 1. Filter by ingredientId
        $res = $this->getJson('/api/v1/inventory-transactions?ingredientId=' . $ing->id);
        $res->assertStatus(200);
        $this->assertCount(2, $res->json('data'));

        // 2. Filter by type=waste
        $res = $this->getJson('/api/v1/inventory-transactions?type=waste');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals($tx2->id, $res->json('data.0.values.id'));

        // 3. Filter by date range (startDate & endDate)
        $res = $this->getJson('/api/v1/inventory-transactions?startDate=2026-06-05&endDate=2026-06-15');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals($tx2->id, $res->json('data.0.values.id'));
    }

    public function test_product_filters()
    {
        $prod1 = Product::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Sweet Bread',
            'price' => 50,
            'unit' => 'pcs',
            'current_stock' => 0 // Out of stock
        ]);

        $prod2 = Product::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Fruit Cake',
            'price' => 200,
            'unit' => 'kg',
            'current_stock' => 5 // In stock
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($this->userA);

        // 1. Search by product_number
        $res = $this->getJson('/api/v1/products?search=' . $prod1->product_number);
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals($prod1->id, $res->json('data.0.values.id'));

        // 2. Filter by unit=kg
        $res = $this->getJson('/api/v1/products?unit=kg');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals($prod2->id, $res->json('data.0.values.id'));

        // 3. Filter by stockStatus=out_of_stock
        $res = $this->getJson('/api/v1/products?stockStatus=out_of_stock');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals($prod1->id, $res->json('data.0.values.id'));

        // 4. Filter by stockStatus=in_stock
        $res = $this->getJson('/api/v1/products?stockStatus=in_stock');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals($prod2->id, $res->json('data.0.values.id'));
    }

    public function test_saved_filters_can_be_created_listed_and_deleted()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->userA);

        $rules = [
            'logical_operator' => 'AND',
            'conditions' => [
                ['field' => 'price', 'operator' => '>', 'value' => 100]
            ]
        ];

        // 1. Create Saved Filter
        $res = $this->postJson('/api/v1/filters/new', [
            'data' => [
                'values' => [
                    'name' => 'High Price Products',
                    'module' => 'products',
                    'isPublic' => true,
                    'rules' => $rules
                ]
            ]
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'data' => [
                    'values' => [
                        'name' => 'High Price Products',
                        'module' => 'products',
                        'isPublic' => true,
                        'rules' => $rules
                    ]
                ]
            ]);

        $filterId = $res->json('data.values.id');

        // 2. List Saved Filters (includes 1 default "All" + 1 user-created)
        $res = $this->getJson('/api/v1/filters?module=Product');
        $res->assertStatus(200);
        $filterIds = collect($res->json('data'))->pluck('values.id')->toArray();
        $this->assertContains($filterId, $filterIds);

        // 3. Delete Saved Filter
        $res = $this->deleteJson('/api/v1/filters/' . $filterId);
        $res->assertStatus(200);

        // Verify missing in DB
        $this->assertDatabaseMissing('saved-filters', ['id' => $filterId]);
    }

    public function test_saved_filters_scoping_is_enforced()
    {
        // Create saved filter for Org B
        $filterB = \App\Modules\Api\V1\SavedFilter\Models\SavedFilter::create([
            'organization_id' => $this->orgB->id,
            'user_id' => $this->userB->id,
            'name' => 'Org B Filter',
            'module' => 'products',
            'rules' => ['conditions' => []],
            'is_public' => true
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($this->userA);

        // 1. Listing should not return Org B's filter (only default "All" should appear)
        $res = $this->getJson('/api/v1/filters?module=Product');
        $res->assertStatus(200);
        $filterIds = collect($res->json('data'))->pluck('values.id')->toArray();
        $this->assertNotContains($filterB->id, $filterIds);

        // 2. Deleting Org B's filter by User A should fail with 404
        $res = $this->deleteJson('/api/v1/filters/' . $filterB->id);
        $res->assertStatus(404);
    }

    public function test_can_apply_saved_filters_to_listing()
    {
        $prod1 = Product::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Cake A',
            'price' => 200,
            'unit' => 'pcs'
        ]);

        $prod2 = Product::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Bread A',
            'price' => 30,
            'unit' => 'pcs'
        ]);

        // Create filter for products: price > 100
        $filter = \App\Modules\Api\V1\SavedFilter\Models\SavedFilter::create([
            'organization_id' => $this->orgA->id,
            'user_id' => $this->userA->id,
            'name' => 'Expensive',
            'module' => 'products',
            'rules' => [
                'logical_operator' => 'AND',
                'conditions' => [
                    ['field' => 'price', 'operator' => '>', 'value' => 100]
                ]
            ],
            'is_public' => false
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($this->userA);

        $res = $this->getJson('/api/v1/products?savedFilterId=' . $filter->id);
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals($prod1->id, $res->json('data.0.values.id'));
    }

    public function test_can_apply_dynamic_rules_to_listing()
    {
        $prod1 = Product::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Cake A',
            'price' => 200,
            'unit' => 'pcs'
        ]);

        $prod2 = Product::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Bread A',
            'price' => 30,
            'unit' => 'pcs'
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($this->userA);

        // Apply rules on-the-fly via array query/body parameter
        $rules = [
            'logical_operator' => 'AND',
            'conditions' => [
                ['field' => 'price', 'operator' => '<', 'value' => 100]
            ]
        ];

        $res = $this->getJson('/api/v1/products?' . http_build_query(['rules' => $rules]));
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals($prod2->id, $res->json('data.0.values.id'));
    }

    public function test_unwhitelisted_fields_throw_validation_error()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->userA);

        // rules with non-whitelisted field "password" or "deleted_at"
        $rules = [
            'logical_operator' => 'AND',
            'conditions' => [
                ['field' => 'password', 'operator' => '=', 'value' => 'leak']
            ]
        ];

        $res = $this->getJson('/api/v1/products?' . http_build_query(['rules' => $rules]));
        
        $res->assertStatus(422)
            ->assertJsonValidationErrors(['rules']);
    }
}
