<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store production clock time so shelf-life expiry is bake-time + hours, not midnight + hours.
     */
    public function up(): void
    {
        if (! Schema::hasTable('production_batches') || ! Schema::hasColumn('production_batches', 'production_date')) {
            return;
        }

        $type = Schema::getColumnType('production_batches', 'production_date');
        if (in_array($type, ['datetime', 'timestamp'], true)) {
            return;
        }

        // MySQL: date -> datetime keeps existing calendar days at 00:00:00
        Schema::table('production_batches', function (Blueprint $table) {
            $table->dateTime('production_date')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('production_batches') || ! Schema::hasColumn('production_batches', 'production_date')) {
            return;
        }

        Schema::table('production_batches', function (Blueprint $table) {
            $table->date('production_date')->nullable(false)->change();
        });
    }
};
