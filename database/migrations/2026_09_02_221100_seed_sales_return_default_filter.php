<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('saved-filters')
            ->where('module', 'sales_returns')
            ->where('is_default', true)
            ->whereNull('organization_id')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('saved-filters')->insert([
            'id' => Str::uuid()->toString(),
            'organization_id' => null,
            'user_id' => null,
            'name' => 'All',
            'module' => 'sales_returns',
            'rules' => json_encode([]),
            'is_public' => true,
            'is_default' => true,
            'header_details' => json_encode([
                ['fieldname' => 'returnNumber', 'fieldlabel' => 'Return #'],
                ['fieldname' => 'branchId', 'fieldlabel' => 'Branch'],
                ['fieldname' => 'returnDate', 'fieldlabel' => 'Return Date'],
                ['fieldname' => 'totalReturnValue', 'fieldlabel' => 'Total Loss'],
                ['fieldname' => 'itemCount', 'fieldlabel' => 'Items'],
                ['fieldname' => 'notes', 'fieldlabel' => 'Notes'],
                ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('saved-filters')
            ->where('module', 'sales_returns')
            ->where('is_default', true)
            ->whereNull('organization_id')
            ->delete();
    }
};
