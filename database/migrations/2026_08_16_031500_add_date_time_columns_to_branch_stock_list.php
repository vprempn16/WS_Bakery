<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Show Date + Time (from updated_at) on BranchStock list headers,
     * and register the view-only crm_fields so DetailView includes them.
     */
    public function up(): void
    {
        $fields = [
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
                'header_details' => json_encode($fields),
                'updated_at' => now(),
            ]);

        if (! Schema::hasTable('crm_fields')) {
            return;
        }

        $now = now();
        $moduleName = 'BranchStock';
        $tableName = 'branch_stocks';

        foreach (
            [
                ['apifieldname' => 'updatedDate', 'fieldname' => 'updated_date', 'fieldlabel' => 'Date', 'fieldtype' => 'date'],
                ['apifieldname' => 'updatedTime', 'fieldname' => 'updated_time', 'fieldlabel' => 'Time', 'fieldtype' => 'time'],
            ] as $def
        ) {
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
                continue;
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
                'tablename' => $tableName,
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
    }

    public function down(): void
    {
        $fields = [
            ['fieldname' => 'branchId', 'fieldlabel' => 'Branch'],
            ['fieldname' => 'productId', 'fieldlabel' => 'Product'],
            ['fieldname' => 'currentStock', 'fieldlabel' => 'Current Stock'],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At'],
        ];

        DB::table('saved-filters')
            ->where('module', 'branch_stocks')
            ->where('is_default', true)
            ->update([
                'header_details' => json_encode($fields),
                'updated_at' => now(),
            ]);

        if (! Schema::hasTable('crm_fields')) {
            return;
        }

        DB::table('crm_fields')
            ->where('modulename', 'BranchStock')
            ->whereIn('apifieldname', ['updatedDate', 'updatedTime'])
            ->update(['deleted' => 1, 'updated_at' => now()]);
    }
};
