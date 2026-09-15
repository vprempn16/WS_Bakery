<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * BranchStock list/detail: show batch expiry date/time (not stock updated_at).
     */
    public function up(): void
    {
        if (! Schema::hasTable('crm_fields')) {
            return;
        }

        $now = now();

        DB::table('crm_fields')
            ->where('modulename', 'BranchStock')
            ->where(function ($q) {
                $q->whereIn('apifieldname', ['updatedDate', 'updatedTime'])
                    ->orWhereIn('fieldname', ['updated_date', 'updated_time', 'updatedDate', 'updatedTime']);
            })
            ->where('deleted', 0)
            ->update([
                'displaytype' => 2,
                'updated_at' => $now,
            ]);

        $this->ensureField([
            'apifieldname' => 'expiryDate',
            'fieldname' => 'expiryDate',
            'fieldlabel' => 'Expiry Date',
            'fieldtype' => 'date',
        ], $now);

        $this->ensureField([
            'apifieldname' => 'expiryTime',
            'fieldname' => 'expiryTime',
            'fieldlabel' => 'Expiry Time',
            'fieldtype' => 'time',
        ], $now);
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_fields')) {
            return;
        }

        $now = now();

        DB::table('crm_fields')
            ->where('modulename', 'BranchStock')
            ->where('apifieldname', 'updatedDate')
            ->update([
                'displaytype' => 3,
                'fieldlabel' => 'Date',
                'updated_at' => $now,
            ]);

        DB::table('crm_fields')
            ->where('modulename', 'BranchStock')
            ->where('apifieldname', 'updatedTime')
            ->update([
                'displaytype' => 3,
                'fieldlabel' => 'Time',
                'updated_at' => $now,
            ]);

        DB::table('crm_fields')
            ->where('modulename', 'BranchStock')
            ->whereIn('apifieldname', ['expiryDate', 'expiryTime'])
            ->update([
                'deleted' => 1,
                'updated_at' => $now,
            ]);
    }

    private function ensureField(array $def, $now): void
    {
        $exists = DB::table('crm_fields')
            ->where('modulename', 'BranchStock')
            ->where(function ($q) use ($def) {
                $q->where('apifieldname', $def['apifieldname'])
                    ->orWhere('fieldname', $def['fieldname']);
            })
            ->where('deleted', 0)
            ->exists();

        if ($exists) {
            DB::table('crm_fields')
                ->where('modulename', 'BranchStock')
                ->where(function ($q) use ($def) {
                    $q->where('apifieldname', $def['apifieldname'])
                        ->orWhere('fieldname', $def['fieldname']);
                })
                ->where('deleted', 0)
                ->update([
                    'fieldlabel' => $def['fieldlabel'],
                    'fieldtype' => $def['fieldtype'],
                    'displaytype' => 3,
                    'mandatory' => 0,
                    'updated_at' => $now,
                ]);

            return;
        }

        $maxSeq = (int) DB::table('crm_fields')
            ->where('modulename', 'BranchStock')
            ->where('deleted', 0)
            ->max('seq');

        DB::table('crm_fields')->insert([
            'id' => Str::uuid()->toString(),
            'modulename' => 'BranchStock',
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
};
