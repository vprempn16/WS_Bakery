<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_batches')) {
            return;
        }

        Schema::table('production_batches', function (Blueprint $table) {
            if (! Schema::hasColumn('production_batches', 'wasted_at')) {
                $table->timestamp('wasted_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('production_batches', 'wasted_quantity')) {
                $table->decimal('wasted_quantity', 10, 2)->nullable()->after('wasted_at');
            }
            if (! Schema::hasColumn('production_batches', 'wasted_reason')) {
                $table->string('wasted_reason')->nullable()->after('wasted_quantity');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('production_batches')) {
            return;
        }

        Schema::table('production_batches', function (Blueprint $table) {
            $drops = [];
            if (Schema::hasColumn('production_batches', 'wasted_reason')) {
                $drops[] = 'wasted_reason';
            }
            if (Schema::hasColumn('production_batches', 'wasted_quantity')) {
                $drops[] = 'wasted_quantity';
            }
            if (Schema::hasColumn('production_batches', 'wasted_at')) {
                $drops[] = 'wasted_at';
            }
            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
