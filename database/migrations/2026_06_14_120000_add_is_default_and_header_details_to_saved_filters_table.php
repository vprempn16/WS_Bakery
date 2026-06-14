<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Make organization_id nullable so global default filters can exist
        Schema::table('saved-filters', function (Blueprint $table) {
            $table->uuid('organization_id')->nullable()->change();
        });

        // Add new columns
        Schema::table('saved-filters', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_public');
            $table->json('header_details')->nullable()->after('is_default');
        });

        // Seed default "All" filters for each module
        $modules = [
            'users' => [
                ['fieldname' => 'id', 'fieldlabel' => 'ID'],
                ['fieldname' => 'firstName', 'fieldlabel' => 'First Name'],
                ['fieldname' => 'lastName', 'fieldlabel' => 'Last Name'],
                ['fieldname' => 'email', 'fieldlabel' => 'Email'],
                ['fieldname' => 'phone', 'fieldlabel' => 'Phone'],
                ['fieldname' => 'role', 'fieldlabel' => 'Role'],
                ['fieldname' => 'organizationId', 'fieldlabel' => 'Organization ID'],
                ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At'],
            ],
            'vendors' => [
                ['fieldname' => 'id', 'fieldlabel' => 'ID'],
                ['fieldname' => 'name', 'fieldlabel' => 'Name'],
                ['fieldname' => 'contactPerson', 'fieldlabel' => 'Contact Person'],
                ['fieldname' => 'phone', 'fieldlabel' => 'Phone'],
                ['fieldname' => 'email', 'fieldlabel' => 'Email'],
                ['fieldname' => 'address', 'fieldlabel' => 'Address'],
                ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At'],
                ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At'],
            ],
            'ingredients' => [
                ['fieldname' => 'id', 'fieldlabel' => 'ID'],
                ['fieldname' => 'name', 'fieldlabel' => 'Name'],
                ['fieldname' => 'unit', 'fieldlabel' => 'Unit'],
                ['fieldname' => 'vendorId', 'fieldlabel' => 'Vendor ID'],
                ['fieldname' => 'minimumStockLevel', 'fieldlabel' => 'Minimum Stock Level'],
                ['fieldname' => 'currentStock', 'fieldlabel' => 'Current Stock'],
                ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At'],
                ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At'],
            ],
            'inventory_transactions' => [
                ['fieldname' => 'id', 'fieldlabel' => 'ID'],
                ['fieldname' => 'ingredientId', 'fieldlabel' => 'Ingredient ID'],
                ['fieldname' => 'type', 'fieldlabel' => 'Type'],
                ['fieldname' => 'quantity', 'fieldlabel' => 'Quantity'],
                ['fieldname' => 'referenceNote', 'fieldlabel' => 'Reference Note'],
                ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At'],
            ],
            'products' => [
                ['fieldname' => 'id', 'fieldlabel' => 'ID'],
                ['fieldname' => 'productNumber', 'fieldlabel' => 'Product Number'],
                ['fieldname' => 'name', 'fieldlabel' => 'Name'],
                ['fieldname' => 'description', 'fieldlabel' => 'Description'],
                ['fieldname' => 'price', 'fieldlabel' => 'Price'],
                ['fieldname' => 'unit', 'fieldlabel' => 'Unit'],
                ['fieldname' => 'shelfLifeDays', 'fieldlabel' => 'Shelf Life Days'],
                ['fieldname' => 'currentStock', 'fieldlabel' => 'Current Stock'],
                ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At'],
                ['fieldname' => 'updatedAt', 'fieldlabel' => 'Updated At'],
            ],
        ];

        $now = now();
        foreach ($modules as $moduleName => $fields) {
            DB::table('saved-filters')->insert([
                'id' => Str::uuid()->toString(),
                'organization_id' => null,
                'user_id' => null,
                'name' => 'All',
                'module' => $moduleName,
                'rules' => json_encode([]),
                'is_public' => true,
                'is_default' => true,
                'header_details' => json_encode($fields),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove default seeded filters
        DB::table('saved-filters')->where('is_default', true)->delete();

        Schema::table('saved-filters', function (Blueprint $table) {
            $table->dropColumn(['is_default', 'header_details']);
        });

        // Revert organization_id to non-nullable
        Schema::table('saved-filters', function (Blueprint $table) {
            $table->uuid('organization_id')->nullable(false)->change();
        });
    }
};
