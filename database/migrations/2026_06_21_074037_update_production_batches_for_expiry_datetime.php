<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First we add the new column
        Schema::table('production_batches', function (Blueprint $table) {
            $table->dateTime('expiry_timestamp')->nullable()->after('expiry_date');
        });

        // Copy data from expiry_date to expiry_timestamp
        DB::statement('UPDATE production_batches SET expiry_timestamp = CAST(expiry_date AS DATETIME)');

        // Now drop the old column and rename
        Schema::table('production_batches', function (Blueprint $table) {
            $table->dropColumn('expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_batches', function (Blueprint $table) {
            $table->date('expiry_date')->nullable()->after('production_date');
        });

        DB::statement('UPDATE production_batches SET expiry_date = CAST(expiry_timestamp AS DATE)');

        Schema::table('production_batches', function (Blueprint $table) {
            $table->dropColumn('expiry_timestamp');
        });
    }
};
