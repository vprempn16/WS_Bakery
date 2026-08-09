<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('crm_fields')
            ->where('modulename', 'Product')
            ->where(function ($q) {
                $q->where('apifieldname', 'productNumber')
                    ->orWhere('fieldname', 'product_number');
            })
            ->where('deleted', 0)
            ->update([
                'displaytype' => 1,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('crm_fields')
            ->where('modulename', 'Product')
            ->where(function ($q) {
                $q->where('apifieldname', 'productNumber')
                    ->orWhere('fieldname', 'product_number');
            })
            ->where('deleted', 0)
            ->update([
                'displaytype' => 3,
                'updated_at' => now(),
            ]);
    }
};
