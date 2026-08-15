<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Product numbers must be unique per organization, not globally.
     * New orgs were failing to create product #1 when another org already used it.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_product_number_unique');
            $table->unique(['organization_id', 'product_number'], 'products_org_product_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_org_product_number_unique');
            $table->unique('product_number', 'products_product_number_unique');
        });
    }
};
