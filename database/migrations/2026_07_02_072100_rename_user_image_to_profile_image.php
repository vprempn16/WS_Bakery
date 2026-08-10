<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // crm_fields is created later (2026_07_11); skip on fresh installs until then.
        if (!Schema::hasTable('crm_fields')) {
            return;
        }

        // 1. Update User module image field
        DB::table('crm_fields')
            ->where('modulename', 'User')
            ->where('fieldname', 'image')
            ->update(['apifieldname' => 'profileImage']);

        // 2. Update Contact module user_image field
        DB::table('crm_fields')
            ->where('modulename', 'Contact')
            ->where('fieldname', 'user_image')
            ->update([
                'apifieldname' => 'profileImage',
                'fieldlabel' => 'Profile Image'
            ]);

        // 3. Update Lead module user_image field
        DB::table('crm_fields')
            ->where('modulename', 'Lead')
            ->where('fieldname', 'user_image')
            ->update([
                'apifieldname' => 'profileImage',
                'fieldlabel' => 'Profile Image'
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('crm_fields')) {
            return;
        }

        // 1. Rollback User module image field
        DB::table('crm_fields')
            ->where('modulename', 'User')
            ->where('fieldname', 'image')
            ->update(['apifieldname' => 'userImage']);

        // 2. Rollback Contact module user_image field
        DB::table('crm_fields')
            ->where('modulename', 'Contact')
            ->where('fieldname', 'user_image')
            ->update([
                'apifieldname' => 'userImage',
                'fieldlabel' => 'User Image'
            ]);

        // 3. Rollback Lead module user_image field
        DB::table('crm_fields')
            ->where('modulename', 'Lead')
            ->where('fieldname', 'user_image')
            ->update([
                'apifieldname' => 'userImage',
                'fieldlabel' => 'User Image'
            ]);
    }
};
