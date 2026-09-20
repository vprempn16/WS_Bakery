<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Own products use shelf life, not pack expiry. CRM must not require expiryDate
     * on every product — Store/UpdateProductRequest still require it for bought.
     */
    public function up(): void
    {
        if (! Schema::hasTable('crm_fields')) {
            return;
        }

        DB::table('crm_fields')
            ->where('modulename', 'Product')
            ->where(function ($q) {
                $q->where('apifieldname', 'expiryDate')
                    ->orWhere('fieldname', 'expiry_date')
                    ->orWhere('fieldname', 'expiryDate');
            })
            ->where('deleted', 0)
            ->update([
                'mandatory' => 0,
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
                $q->where('apifieldname', 'expiryDate')
                    ->orWhere('fieldname', 'expiry_date')
                    ->orWhere('fieldname', 'expiryDate');
            })
            ->update([
                'mandatory' => 1,
                'updated_at' => now(),
            ]);
    }
};
