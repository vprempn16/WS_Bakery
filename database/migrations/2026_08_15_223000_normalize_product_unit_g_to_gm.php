<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'unit')) {
            return;
        }

        DB::table('products')->where('unit', 'g')->update(['unit' => 'gm']);
    }

    public function down(): void
    {
        // Irreversible data normalization
    }
};
