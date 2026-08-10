<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'image')) {
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('image', 'profileImage');
            });
        }
        
        if (Schema::hasColumn('leads', 'user_image')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->renameColumn('user_image', 'profileImage');
            });
        }
        
        if (Schema::hasColumn('contacts', 'user_image')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->renameColumn('user_image', 'profileImage');
            });
        }

        if (Schema::hasColumn('products', 'profile_image')) {
            Schema::table('products', function (Blueprint $table) {
                $table->renameColumn('profile_image', 'profileImage');
            });
        }

        // crm_fields is created later (2026_07_11); skip on fresh installs until then.
        if (Schema::hasTable('crm_fields')) {
            DB::table('crm_fields')
                ->whereIn('modulename', ['Lead', 'Contact'])
                ->where('fieldname', 'user_image')
                ->update(['fieldname' => 'profileImage']);

            DB::table('crm_fields')
                ->where('modulename', 'User')
                ->where('fieldname', 'image')
                ->update(['fieldname' => 'profileImage']);

            DB::table('crm_fields')
                ->where('modulename', 'Product')
                ->where('fieldname', 'profile_image')
                ->update(['fieldname' => 'profileImage']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'profileImage')) {
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('profileImage', 'image');
            });
        }
        
        if (Schema::hasColumn('leads', 'profileImage')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->renameColumn('profileImage', 'user_image');
            });
        }

        if (Schema::hasColumn('contacts', 'profileImage')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->renameColumn('profileImage', 'user_image');
            });
        }

        if (Schema::hasColumn('products', 'profileImage')) {
            Schema::table('products', function (Blueprint $table) {
                $table->renameColumn('profileImage', 'profile_image');
            });
        }

        if (Schema::hasTable('crm_fields')) {
            DB::table('crm_fields')
                ->whereIn('modulename', ['Lead', 'Contact'])
                ->where('fieldname', 'profileImage')
                ->update(['fieldname' => 'user_image']);

            DB::table('crm_fields')
                ->where('modulename', 'User')
                ->where('fieldname', 'profileImage')
                ->update(['fieldname' => 'image']);

            DB::table('crm_fields')
                ->where('modulename', 'Product')
                ->where('fieldname', 'profileImage')
                ->update(['fieldname' => 'profile_image']);
        }
    }
};
