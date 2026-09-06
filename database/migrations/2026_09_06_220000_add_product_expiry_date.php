<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'expiry_date')) {
            Schema::table('products', function (Blueprint $table) {
                $table->date('expiry_date')->nullable()->after('shelf_life');
            });
        }

        $exists = DB::table('crm_fields')
            ->where('modulename', 'Product')
            ->where(function ($q) {
                $q->where('fieldname', 'expiry_date')
                    ->orWhere('apifieldname', 'expiryDate');
            })
            ->where('deleted', 0)
            ->exists();

        if (! $exists) {
            $seq = (int) DB::table('crm_fields')
                ->where('modulename', 'Product')
                ->where('deleted', 0)
                ->max('seq');

            DB::table('crm_fields')->insert([
                'id' => (string) Str::uuid(),
                'modulename' => 'Product',
                'fieldname' => 'expiry_date',
                'fieldlabel' => 'Expired Date',
                'fieldtype' => 'date',
                'tablename' => 'products',
                'mandatory' => 0,
                'apifieldname' => 'expiryDate',
                'displaytype' => 1,
                'is_custom_field' => 0,
                'organization_id' => 'default',
                'deleted' => 0,
                'seq' => $seq + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('crm_fields')
            ->where('modulename', 'Product')
            ->where(function ($q) {
                $q->where('fieldname', 'expiry_date')
                    ->orWhere('apifieldname', 'expiryDate');
            })
            ->update(['deleted' => 1, 'updated_at' => now()]);

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'expiry_date')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('expiry_date');
            });
        }
    }
};
