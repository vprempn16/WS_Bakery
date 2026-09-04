<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products') || Schema::hasColumn('products', 'product_image')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('product_image')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'product_image')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('product_image');
        });
    }
};
