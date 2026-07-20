<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * roles.status: store 1|0 (not Active|Inactive strings).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        DB::table('roles')->whereIn('status', ['Active', 'active', '1', 1])->update(['status' => 1]);
        DB::table('roles')->whereIn('status', ['Inactive', 'inactive', '0', 0])->update(['status' => 0]);
        // Any other legacy value → inactive
        DB::table('roles')
            ->whereNotIn('status', [0, 1, '0', '1'])
            ->update(['status' => 0]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        DB::table('roles')->whereIn('status', [1, '1'])->update(['status' => 'Active']);
        DB::table('roles')->whereIn('status', [0, '0'])->update(['status' => 'Inactive']);
    }
};
