<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * SalesReturn is header + items now. Soft-delete legacy flat fields on the
 * header module (productId, quantity, unitPrice, returnValue) so BKModel
 * validateBeforeSave no longer requires them on create.
 *
 * Timestamp 224000 (not 223000) so it does not collide with
 * hide_created_at_on_plan_and_material_lists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_fields')) {
            return;
        }

        $now = now();

        DB::table('crm_fields')
            ->where('modulename', 'SalesReturn')
            ->whereIn('apifieldname', ['productId', 'quantity', 'unitPrice', 'returnValue'])
            ->where('deleted', 0)
            ->update([
                'deleted' => 1,
                'mandatory' => 0,
                'updated_at' => $now,
            ]);

        // Ensure SalesReturnItem CRM fields exist for related tab / future forms
        $itemFields = [
            ['apifieldname' => 'productId', 'fieldname' => 'product_id', 'fieldlabel' => 'Product', 'fieldtype' => 'relationPickList', 'displaytype' => 1, 'mandatory' => 1],
            ['apifieldname' => 'quantity', 'fieldname' => 'quantity', 'fieldlabel' => 'Quantity', 'fieldtype' => 'number', 'displaytype' => 1, 'mandatory' => 1],
            ['apifieldname' => 'unit', 'fieldname' => 'unit', 'fieldlabel' => 'Unit', 'fieldtype' => 'text', 'displaytype' => 3, 'mandatory' => 0],
            ['apifieldname' => 'pieces', 'fieldname' => 'pieces', 'fieldlabel' => 'Pieces', 'fieldtype' => 'number', 'displaytype' => 1, 'mandatory' => 0],
            ['apifieldname' => 'unitPrice', 'fieldname' => 'unit_price', 'fieldlabel' => 'Unit Price', 'fieldtype' => 'currency', 'displaytype' => 3, 'mandatory' => 0],
            ['apifieldname' => 'returnValue', 'fieldname' => 'return_value', 'fieldlabel' => 'Line Loss', 'fieldtype' => 'currency', 'displaytype' => 3, 'mandatory' => 0],
        ];

        foreach ($itemFields as $def) {
            $exists = DB::table('crm_fields')
                ->where('modulename', 'SalesReturnItem')
                ->where('apifieldname', $def['apifieldname'])
                ->where('deleted', 0)
                ->exists();

            if ($exists) {
                DB::table('crm_fields')
                    ->where('modulename', 'SalesReturnItem')
                    ->where('apifieldname', $def['apifieldname'])
                    ->update([
                        'fieldlabel' => $def['fieldlabel'],
                        'fieldtype' => $def['fieldtype'],
                        'displaytype' => $def['displaytype'],
                        'mandatory' => $def['mandatory'],
                        'deleted' => 0,
                        'updated_at' => $now,
                    ]);
                continue;
            }

            $maxSeq = (int) DB::table('crm_fields')
                ->where('modulename', 'SalesReturnItem')
                ->where('deleted', 0)
                ->max('seq');

            DB::table('crm_fields')->insert([
                'id' => Str::uuid()->toString(),
                'modulename' => 'SalesReturnItem',
                'fieldname' => $def['fieldname'],
                'fieldlabel' => $def['fieldlabel'],
                'fieldtype' => $def['fieldtype'],
                'tablename' => 'sales_return_items',
                'mandatory' => $def['mandatory'],
                'apifieldname' => $def['apifieldname'],
                'displaytype' => $def['displaytype'],
                'is_custom_field' => 0,
                'seq' => $maxSeq + 1,
                'deleted' => 0,
                'organization_id' => 'default',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_fields')) {
            return;
        }

        DB::table('crm_fields')
            ->where('modulename', 'SalesReturn')
            ->whereIn('apifieldname', ['productId', 'quantity', 'unitPrice', 'returnValue'])
            ->update([
                'deleted' => 0,
                'updated_at' => now(),
            ]);
    }
};
