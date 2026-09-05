<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Product list: Shelf Status badge column (Expired / Expiring / Fresh).
     */
    public function up(): void
    {
        $headers = [
            ['fieldname' => 'productNumber', 'fieldlabel' => 'Product Number'],
            ['fieldname' => 'name', 'fieldlabel' => 'Name'],
            ['fieldname' => 'price', 'fieldlabel' => 'Price'],
            ['fieldname' => 'unit', 'fieldlabel' => 'Unit'],
            ['fieldname' => 'status', 'fieldlabel' => 'Status'],
            ['fieldname' => 'shelfLife', 'fieldlabel' => 'Shelf Life'],
            ['fieldname' => 'shelfStatus', 'fieldlabel' => 'Shelf Status'],
            ['fieldname' => 'currentStock', 'fieldlabel' => 'Current Stock'],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At'],
        ];

        DB::table('saved-filters')
            ->where('module', 'products')
            ->where('is_default', true)
            ->update([
                'header_details' => json_encode($headers),
                'updated_at' => now(),
            ]);

        if (! Schema::hasTable('crm_fields')) {
            return;
        }

        $now = now();
        $moduleName = 'Product';
        $def = [
            'apifieldname' => 'shelfStatus',
            'fieldname' => 'shelf_status',
            'fieldlabel' => 'Shelf Status',
            'fieldtype' => 'text',
        ];

        $exists = DB::table('crm_fields')
            ->where('modulename', $moduleName)
            ->where(function ($q) use ($def) {
                $q->where('apifieldname', $def['apifieldname'])
                    ->orWhere('fieldname', $def['fieldname']);
            })
            ->where('deleted', 0)
            ->exists();

        if ($exists) {
            DB::table('crm_fields')
                ->where('modulename', $moduleName)
                ->where(function ($q) use ($def) {
                    $q->where('apifieldname', $def['apifieldname'])
                        ->orWhere('fieldname', $def['fieldname']);
                })
                ->update([
                    'fieldlabel' => $def['fieldlabel'],
                    'fieldtype' => $def['fieldtype'],
                    'displaytype' => 3,
                    'mandatory' => 0,
                    'deleted' => 0,
                    'updated_at' => $now,
                ]);

            return;
        }

        $maxSeq = (int) DB::table('crm_fields')
            ->where('modulename', $moduleName)
            ->where('deleted', 0)
            ->max('seq');

        DB::table('crm_fields')->insert([
            'id' => Str::uuid()->toString(),
            'modulename' => $moduleName,
            'fieldname' => $def['fieldname'],
            'fieldlabel' => $def['fieldlabel'],
            'fieldtype' => $def['fieldtype'],
            'tablename' => 'products',
            'mandatory' => 0,
            'apifieldname' => $def['apifieldname'],
            'displaytype' => 3,
            'is_custom_field' => 0,
            'seq' => $maxSeq + 1,
            'deleted' => 0,
            'organization_id' => 'default',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_fields')) {
            DB::table('crm_fields')
                ->where('modulename', 'Product')
                ->where('apifieldname', 'shelfStatus')
                ->update(['deleted' => 1, 'updated_at' => now()]);
        }
    }
};
