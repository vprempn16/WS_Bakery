<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'product_source')) {
                $table->string('product_source', 20)->default('own')->after('status');
                $table->index(['organization_id', 'product_source']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'product_source')) {
                $table->dropIndex(['organization_id', 'product_source']);
                $table->dropColumn('product_source');
            }
        });
    }
};
