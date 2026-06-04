<?php

namespace Database\Seeders;

use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Organization
        $organization = Organization::create([
            'name' => 'Grand Bakery WMS',
            'email' => 'contact@grandbakery.com',
            'phone' => '+919876543210',
            'address' => '123 Main Bazaar Road, Bangalore, Karnataka',
        ]);

        // 2. Seed Owner User
        User::create([
            'organization_id' => $organization->id,
            'name' => 'Bakery Owner',
            'email' => 'owner@bakerywms.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
        ]);

        // 3. Seed Warehouse Manager User
        User::create([
            'organization_id' => $organization->id,
            'name' => 'Warehouse Manager',
            'email' => 'manager@bakerywms.com',
            'password' => Hash::make('password'),
            'role' => 'warehouse_manager',
        ]);

        // 4. Seed Branch Staff User
        User::create([
            'organization_id' => $organization->id,
            'name' => 'Branch Staff',
            'email' => 'staff@bakerywms.com',
            'password' => Hash::make('password'),
            'role' => 'branch_staff',
        ]);
    }
}
