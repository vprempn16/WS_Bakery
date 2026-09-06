<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branches')) {
            return;
        }

        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'pos_discount_amount')) {
                $table->decimal('pos_discount_amount', 12, 2)->default(0)->after('type');
            }
            if (! Schema::hasColumn('branches', 'pos_tax_percent')) {
                $table->decimal('pos_tax_percent', 5, 2)->default(0)->after('pos_discount_amount');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('branches')) {
            return;
        }

        Schema::table('branches', function (Blueprint $table) {
            if (Schema::hasColumn('branches', 'pos_tax_percent')) {
                $table->dropColumn('pos_tax_percent');
            }
            if (Schema::hasColumn('branches', 'pos_discount_amount')) {
                $table->dropColumn('pos_discount_amount');
            }
        });
    }
};
