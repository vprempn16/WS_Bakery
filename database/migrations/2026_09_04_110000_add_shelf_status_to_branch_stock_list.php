<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Add Shelf Status column to BranchStock list (warn-only; not FIFO-accurate).
     */
    public function up(): void
    {
        $headers = [
            ['fieldname' => 'branchId', 'fieldlabel' => 'Branch'],
            ['fieldname' => 'productId', 'fieldlabel' => 'Product'],
            ['fieldname' => 'currentStock', 'fieldlabel' => 'Current Stock'],
            ['fieldname' => 'shelfStatus', 'fieldlabel' => 'Shelf Status'],
            ['fieldname' => 'updatedDate', 'fieldlabel' => 'Date'],
            ['fieldname' => 'updatedTime', 'fieldlabel' => 'Time'],
        ];

        DB::table('saved-filters')
            ->where('module', 'branch_stocks')
            ->where('is_default', true)
            ->update([
                'header_details' => json_encode($headers),
                'updated_at' => now(),
            ]);

        if (! Schema::hasTable('crm_fields')) {
            return;
        }

        $now = now();
        $moduleName = 'BranchStock';
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
            'tablename' => 'branch_stocks',
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
        $headers = [
            ['fieldname' => 'branchId', 'fieldlabel' => 'Branch'],
            ['fieldname' => 'productId', 'fieldlabel' => 'Product'],
            ['fieldname' => 'currentStock', 'fieldlabel' => 'Current Stock'],
            ['fieldname' => 'updatedDate', 'fieldlabel' => 'Date'],
            ['fieldname' => 'updatedTime', 'fieldlabel' => 'Time'],
        ];

        DB::table('saved-filters')
            ->where('module', 'branch_stocks')
            ->where('is_default', true)
            ->update([
                'header_details' => json_encode($headers),
                'updated_at' => now(),
            ]);

        if (! Schema::hasTable('crm_fields')) {
            return;
        }

        DB::table('crm_fields')
            ->where('modulename', 'BranchStock')
            ->where('apifieldname', 'shelfStatus')
            ->update(['deleted' => 1, 'updated_at' => now()]);
    }
};
