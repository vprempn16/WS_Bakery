<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['shelf_life_days', 'shelf_life_hours']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('shelf_life')->nullable()->after('status');
        });

        // Soft-delete legacy Product CRM fields (days/hours date-time era)
        DB::table('crm_fields')
            ->where('modulename', 'Product')
            ->whereIn('apifieldname', ['shelfLifeDays', 'shelfLifeHours'])
            ->update(['deleted' => 1, 'displaytype' => 2]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('shelf_life');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->date('shelf_life_days')->nullable();
            $table->time('shelf_life_hours')->nullable();
        });
    }
};
