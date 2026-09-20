<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->decimal('quantity_required', 10, 2)->nullable()->change();
            if (! Schema::hasColumn('recipes', 'quantity_pending')) {
                $table->boolean('quantity_pending')->default(false)->after('quantity_required');
            }
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            if (Schema::hasColumn('recipes', 'quantity_pending')) {
                $table->dropColumn('quantity_pending');
            }
            $table->decimal('quantity_required', 10, 2)->nullable(false)->change();
        });
    }
};
