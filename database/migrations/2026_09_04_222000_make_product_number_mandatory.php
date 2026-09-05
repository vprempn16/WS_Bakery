<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Product Number is mandatory on create/edit (crm_fields + form metadata).
     */
    public function up(): void
    {
        if (! Schema::hasTable('crm_fields')) {
            return;
        }

        DB::table('crm_fields')
            ->where('modulename', 'Product')
            ->where(function ($q) {
                $q->where('apifieldname', 'productNumber')
                    ->orWhere('fieldname', 'product_number');
            })
            ->where('deleted', 0)
            ->update([
                'mandatory' => 1,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_fields')) {
            return;
        }

        DB::table('crm_fields')
            ->where('modulename', 'Product')
            ->where(function ($q) {
                $q->where('apifieldname', 'productNumber')
                    ->orWhere('fieldname', 'product_number');
            })
            ->update([
                'mandatory' => 0,
                'updated_at' => now(),
            ]);
    }
};
