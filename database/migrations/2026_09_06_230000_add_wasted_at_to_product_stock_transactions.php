<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_stock_transactions') && ! Schema::hasColumn('product_stock_transactions', 'wasted_at')) {
            Schema::table('product_stock_transactions', function (Blueprint $table) {
                $table->timestamp('wasted_at')->nullable()->after('expiry_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_stock_transactions') && Schema::hasColumn('product_stock_transactions', 'wasted_at')) {
            Schema::table('product_stock_transactions', function (Blueprint $table) {
                $table->dropColumn('wasted_at');
            });
        }
    }
};
