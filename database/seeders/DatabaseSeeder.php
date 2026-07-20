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
        // Prefer setup.sh for superadmin — this seeder is optional demo data.
        $organization = Organization::firstOrCreate(
            ['email' => 'contact@grandbakery.com'],
            [
                'name' => 'Grand Bakery WMS',
                'phone' => '+919876543210',
                'address' => '123 Main Bazaar Road, Bangalore, Karnataka',
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@bakerywms.com'],
            [
                'organization_id' => $organization->id,
                'first_name' => 'Bakery',
                'last_name' => 'Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_active' => 1,
            ]
        );

        User::updateOrCreate(
            ['email' => 'manager@bakerywms.com'],
            [
                'organization_id' => $organization->id,
                'first_name' => 'Warehouse',
                'last_name' => 'Manager',
                'password' => Hash::make('password'),
                'role' => 'staff',
                'is_active' => 1,
            ]
        );

        User::updateOrCreate(
            ['email' => 'staff@bakerywms.com'],
            [
                'organization_id' => $organization->id,
                'first_name' => 'Branch',
                'last_name' => 'Staff',
                'password' => Hash::make('password'),
                'role' => 'staff',
                'is_active' => 1,
            ]
        );
    }
}
