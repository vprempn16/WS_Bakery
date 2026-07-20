<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Hide old single-product header fields (moved to branch_transfer_items)
        DB::table('crm_fields')
            ->where('modulename', 'BranchTransfer')
            ->whereIn('apifieldname', ['productId', 'quantity'])
            ->where('deleted', 0)
            ->update([
                'displaytype' => 2,
                'mandatory' => 0,
                'updated_at' => now(),
            ]);

        DB::table('crm_fields')
            ->where('modulename', 'BranchTransfer')
            ->where('apifieldname', 'branchId')
            ->where('deleted', 0)
            ->update([
                'fieldlabel' => 'To Branch',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('crm_fields')
            ->where('modulename', 'BranchTransfer')
            ->where('apifieldname', 'productId')
            ->update([
                'displaytype' => 1,
                'mandatory' => 1,
                'updated_at' => now(),
            ]);

        DB::table('crm_fields')
            ->where('modulename', 'BranchTransfer')
            ->where('apifieldname', 'quantity')
            ->update([
                'displaytype' => 1,
                'mandatory' => 1,
                'updated_at' => now(),
            ]);

        DB::table('crm_fields')
            ->where('modulename', 'BranchTransfer')
            ->where('apifieldname', 'branchId')
            ->update([
                'fieldlabel' => 'Branch',
                'updated_at' => now(),
            ]);
    }
};
