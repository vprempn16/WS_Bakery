<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Product: category + shelf life mandatory.
     * ProductionBatch: productionDate becomes datetime (date + time for shelf-life maths).
     */
    public function up(): void
    {
        if (! Schema::hasTable('crm_fields')) {
            return;
        }

        DB::table('crm_fields')
            ->where('modulename', 'Product')
            ->where(function ($q) {
                $q->where('apifieldname', 'category')
                    ->orWhere('fieldname', 'category');
            })
            ->where('deleted', 0)
            ->update([
                'mandatory' => 1,
                'updated_at' => now(),
            ]);

        DB::table('crm_fields')
            ->where('modulename', 'Product')
            ->where(function ($q) {
                $q->where('apifieldname', 'shelfLife')
                    ->orWhere('fieldname', 'shelf_life')
                    ->orWhere('fieldname', 'shelfLife');
            })
            ->where('deleted', 0)
            ->update([
                'mandatory' => 1,
                'updated_at' => now(),
            ]);

        DB::table('crm_fields')
            ->where('modulename', 'Product')
            ->where(function ($q) {
                $q->where('apifieldname', 'expiryDate')
                    ->orWhere('fieldname', 'expiry_date')
                    ->orWhere('fieldname', 'expiryDate');
            })
            ->where('deleted', 0)
            ->update([
                'mandatory' => 1,
                'updated_at' => now(),
            ]);

        DB::table('crm_fields')
            ->where('modulename', 'ProductionBatch')
            ->where(function ($q) {
                $q->where('apifieldname', 'productionDate')
                    ->orWhere('fieldname', 'production_date')
                    ->orWhere('fieldname', 'productionDate');
            })
            ->where('deleted', 0)
            ->update([
                'fieldlabel' => 'Production Date & Time',
                'fieldtype' => 'datetime',
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
                $q->where('apifieldname', 'category')
                    ->orWhere('fieldname', 'category');
            })
            ->update([
                'mandatory' => 0,
                'updated_at' => now(),
            ]);

        DB::table('crm_fields')
            ->where('modulename', 'Product')
            ->where(function ($q) {
                $q->where('apifieldname', 'shelfLife')
                    ->orWhere('fieldname', 'shelf_life')
                    ->orWhere('fieldname', 'shelfLife');
            })
            ->update([
                'mandatory' => 0,
                'updated_at' => now(),
            ]);

        DB::table('crm_fields')
            ->where('modulename', 'Product')
            ->where(function ($q) {
                $q->where('apifieldname', 'expiryDate')
                    ->orWhere('fieldname', 'expiry_date')
                    ->orWhere('fieldname', 'expiryDate');
            })
            ->update([
                'mandatory' => 0,
                'updated_at' => now(),
            ]);

        DB::table('crm_fields')
            ->where('modulename', 'ProductionBatch')
            ->where(function ($q) {
                $q->where('apifieldname', 'productionDate')
                    ->orWhere('fieldname', 'production_date')
                    ->orWhere('fieldname', 'productionDate');
            })
            ->update([
                'fieldlabel' => 'Production Date',
                'fieldtype' => 'date',
                'updated_at' => now(),
            ]);
    }
};
