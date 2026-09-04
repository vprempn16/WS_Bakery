<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * itemCount is computed (items relation count), not a billings column.
     * displaytype 1 caused create validation to write item_count=0 and fail the INSERT.
     */
    public function up(): void
    {
        if (! Schema::hasTable('crm_fields')) {
            return;
        }

        DB::table('crm_fields')
            ->where('modulename', 'Billing')
            ->where(function ($q) {
                $q->where('apifieldname', 'itemCount')
                    ->orWhere('fieldname', 'item_count');
            })
            ->where('deleted', 0)
            ->update([
                'displaytype' => 3,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_fields')) {
            return;
        }

        DB::table('crm_fields')
            ->where('modulename', 'Billing')
            ->where(function ($q) {
                $q->where('apifieldname', 'itemCount')
                    ->orWhere('fieldname', 'item_count');
            })
            ->where('deleted', 0)
            ->update([
                'displaytype' => 1,
                'updated_at' => now(),
            ]);
    }
};
