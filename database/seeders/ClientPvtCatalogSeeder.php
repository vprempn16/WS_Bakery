<?php

namespace Database\Seeders;

use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\User\Models\User;
use App\Modules\Api\V1\Vendor\Models\Vendor;
use App\Services\DefaultCatalogService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder for Client-PVT Ingredients and Products Catalog.
 * 
 * Configurable admin variables: Set $adminEmail and $adminPassword below.
 * Seeding will ONLY happen for that admin user's organization!
 *
 * Seeding copies client-pvt/images/{product number}.jpg into public storage when that file exists.
 * Run via:
 *   php8.3 artisan db:seed --class=Database\\Seeders\\ClientPvtCatalogSeeder
 */
class ClientPvtCatalogSeeder extends Seeder
{
    /**
     * Admin credentials to target specific organization.
     * Configure these variables as needed:
     */
    public static string $adminEmail = 'premnath@atomlines.com';
    public static string $adminPassword = '';

    public function run(): void
    {
        $email = env('SEED_ADMIN_EMAIL', self::$adminEmail);
        $password = env('SEED_ADMIN_PASSWORD', self::$adminPassword);

        $this->command?->info("Targeting admin user: {$email}");

        /** @var User|null $admin */
        $admin = User::withoutGlobalScopes()->where('email', $email)->first();

        if (! $admin) {
            $this->command?->error("Admin user with email '{$email}' not found. Seeding aborted to protect other organizations.");
            return;
        }

        if (! empty($password)) {
            $admin->password = Hash::make($password);
            $admin->save();
            $this->command?->info("Admin password updated successfully.");
        }

        $orgId = (string) $admin->organization_id;
        $org = Organization::withoutGlobalScopes()->find($orgId);

        if (! $org) {
            $this->command?->error("Organization with ID '{$orgId}' for admin '{$email}' not found.");
            return;
        }

        $this->command?->info("Seeding ingredients and products ONLY for Organization: {$org->name} (ID: {$org->id})");

        // Seed or retrieve default vendor for this organization
        $vendor = Vendor::withoutGlobalScopes()->where('organization_id', $orgId)->first();
        if (! $vendor) {
            $vendor = Vendor::withoutGlobalScopes()->create([
                'organization_id' => $orgId,
                'name' => 'Primary Bakery Supplier',
                'contact_person' => 'Supplier Contact',
                'phone' => '+919876543210',
                'email' => 'supplier@bakery.test',
                'address' => 'Chennai, Tamil Nadu',
            ]);
        }

        // Seeds all ingredients from client-pvt/ingredients.php and products from client-pvt/products.php
        app(DefaultCatalogService::class)->seedForOrganization((string) $orgId, true, $vendor->id);

        $this->command?->info("Successfully seeded catalog from client-pvt ONLY for Organization '{$org->name}'!");
    }
}
