<?php

namespace Tests\Feature;

use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_organization()
    {
        $response = $this->postJson('/api/v1/organization/new', [
            'data' => [
                'values' => [
                    'name' => 'Demo',
                    'description' => '',
                    'firstUser' => [
                        'firstName' => 'Arif',
                        'lastName' => 'imran1',
                        'email' => 'demoarif@example.com',
                        'phoneNumber' => '+918012033834',
                        'password' => 'Admin@123',
                        'confirmPassword' => 'Admin@123'
                    ]
                ]
            ]
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'first_name',
                        'last_name',
                        'email',
                        'phone_number',
                        'role',
                        'organization' => [
                            'id',
                            'name'
                        ]
                    ]
                ]
            ])
            ->assertJson([
                'status' => true,
                'message' => 'Organization created successfully.',
                'data' => [
                    'user' => [
                        'first_name' => 'Arif',
                        'last_name' => 'imran1',
                        'email' => 'demoarif@example.com',
                        'phone_number' => '+918012033834',
                        'role' => 'owner',
                        'organization' => [
                            'name' => 'Demo'
                        ]
                    ]
                ]
            ]);

        $this->assertDatabaseHas('organizations', [
            'name' => 'Demo',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'demoarif@example.com',
            'first_name' => 'Arif',
            'last_name' => 'imran1',
            'role' => 'owner'
        ]);
    }

    public function test_can_get_organization_by_id()
    {
        $org = Organization::create([
            'name' => 'WS Bakery',
            'email' => 'wsbakery12@gmail.com',
        ]);

        $response = $this->getJson("/api/v1/organization/{$org->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'values' => [
                        'id' => $org->id,
                        'name' => 'WS Bakery',
                        'email' => 'wsbakery12@gmail.com',
                    ]
                ]
            ]);
    }

    public function test_can_update_organization()
    {
        $org = Organization::create([
            'name' => 'WS Bakery',
            'email' => 'wsbakery12@gmail.com',
        ]);

        $response = $this->putJson("/api/v1/organization/{$org->id}", [
            'data' => [
                'values' => [
                    'name' => 'WS Bakery Updated',
                    'description' => 'New description',
                    'email' => 'updated@gmail.com',
                ]
            ]
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'values' => [
                        'id' => $org->id,
                        'name' => 'WS Bakery Updated',
                        'description' => 'New description',
                        'email' => 'updated@gmail.com',
                    ]
                ]
            ]);
    }

    public function test_can_delete_organization()
    {
        $org = Organization::create([
            'name' => 'WS Bakery',
        ]);

        $response = $this->deleteJson("/api/v1/organization/{$org->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('organizations', ['id' => $org->id]);
    }

    public function test_can_search_organizations()
    {
        Organization::create(['name' => 'First Bakery', 'email' => 'first@gmail.com']);
        Organization::create(['name' => 'Second Sweet Shop', 'email' => 'second@gmail.com']);

        $response = $this->getJson('/api/v1/organization/search?query=Bakery');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_create_user_and_receive_token()
    {
        $org = Organization::create([
            'name' => 'WS Bakery',
        ]);

        $response = $this->postJson('/api/v1/settings/User/new', [
            'data' => [
                'values' => [
                    'lastName' => 'Nath',
                    'firstName' => 'Prem',
                    'role' => 'admin',
                    'email' => 'premnath@atomlines.com',
                    'phone' => '+91-9876543210',
                    'password' => 'Prem@2828',
                    'confirmPassword' => 'Prem@2828',
                    'organizationId' => $org->id,
                ]
            ]
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'values' => [
                        'id',
                        'firstName',
                        'lastName',
                        'email',
                        'phone',
                        'role',
                        'organizationId',
                        'token',
                    ]
                ]
            ])
            ->assertJson([
                'data' => [
                    'values' => [
                        'firstName' => 'Prem',
                        'lastName' => 'Nath',
                        'email' => 'premnath@atomlines.com',
                        'phone' => '+91-9876543210',
                        'role' => 'admin',
                        'organizationId' => $org->id,
                    ]
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'premnath@atomlines.com',
            'first_name' => 'Prem',
            'last_name' => 'Nath',
        ]);
    }

    public function test_can_get_user_by_id()
    {
        $org = Organization::create(['name' => 'WS Bakery']);
        $user = User::create([
            'organization_id' => $org->id,
            'first_name' => 'Prem',
            'last_name' => 'Nath',
            'email' => 'premnath@atomlines.com',
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);

        $response = $this->getJson("/api/v1/settings/User/{$user->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'values' => [
                        'id' => $user->id,
                        'firstName' => 'Prem',
                        'lastName' => 'Nath',
                        'email' => 'premnath@atomlines.com',
                    ]
                ]
            ]);
    }

    public function test_can_update_user()
    {
        $org = Organization::create(['name' => 'WS Bakery']);
        $user = User::create([
            'organization_id' => $org->id,
            'first_name' => 'Prem',
            'last_name' => 'Nath',
            'email' => 'premnath@atomlines.com',
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);

        $response = $this->putJson("/api/v1/settings/User/{$user->id}", [
            'data' => [
                'values' => [
                    'lastName' => 'Nath Updated',
                    'firstName' => 'Prem Updated',
                    'role' => 'admin',
                    'email' => 'updateduser@atomlines.com',
                    'phone' => '+91-9876543210',
                    'organizationId' => $org->id,
                ]
            ]
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'values' => [
                        'id' => $user->id,
                        'firstName' => 'Prem Updated',
                        'lastName' => 'Nath Updated',
                        'email' => 'updateduser@atomlines.com',
                    ]
                ]
            ]);
    }

    public function test_can_delete_user()
    {
        $org = Organization::create(['name' => 'WS Bakery']);
        $user = User::create([
            'organization_id' => $org->id,
            'first_name' => 'Prem',
            'last_name' => 'Nath',
            'email' => 'premnath@atomlines.com',
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);

        $response = $this->deleteJson("/api/v1/settings/User/{$user->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_can_list_users()
    {
        $org = Organization::create(['name' => 'WS Bakery']);
        User::create([
            'organization_id' => $org->id,
            'first_name' => 'Prem',
            'last_name' => 'Nath',
            'email' => 'premnath@atomlines.com',
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);

        $response = $this->getJson('/api/v1/settings/User');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
}
