<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'status')) {
                $table->string('status', 20)->default('active');
                $table->index(['organization_id', 'status']);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'status')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'status']);
            $table->dropColumn('status');
        });
    }
};
