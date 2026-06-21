<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('tier')->nullable()->after('unit'); // tier_1, tier_2, tier_3
            $table->integer('shelf_life_hours')->nullable()->after('shelf_life_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('tier');
            $table->dropColumn('shelf_life_hours');
        });
    }
};
