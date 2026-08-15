<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->date('shelf_life_date_new')->nullable()->after('shelf_life_days');
            $table->time('shelf_life_time_new')->nullable()->after('shelf_life_hours');
        });

        // Existing numeric durations cannot be safely converted to fixed calendar values.
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['shelf_life_days', 'shelf_life_hours']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('shelf_life_date_new', 'shelf_life_days');
            $table->renameColumn('shelf_life_time_new', 'shelf_life_hours');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('shelf_life_days_new')->nullable();
            $table->unsignedInteger('shelf_life_hours_new')->nullable();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['shelf_life_days', 'shelf_life_hours']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('shelf_life_days_new', 'shelf_life_days');
            $table->renameColumn('shelf_life_hours_new', 'shelf_life_hours');
        });
    }
};
