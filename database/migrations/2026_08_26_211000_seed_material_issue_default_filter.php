<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('saved-filters')
            ->where('module', 'material_issues')
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
            'module' => 'material_issues',
            'rules' => json_encode([]),
            'is_public' => true,
            'is_default' => true,
            'header_details' => json_encode([
                ['fieldname' => 'id', 'fieldlabel' => 'ID'],
                ['fieldname' => 'issueNumber', 'fieldlabel' => 'Withdrawal Number'],
                ['fieldname' => 'issueDate', 'fieldlabel' => 'Withdrawal Date'],
                ['fieldname' => 'status', 'fieldlabel' => 'Status'],
                ['fieldname' => 'notes', 'fieldlabel' => 'Notes'],
                ['fieldname' => 'createdBy', 'fieldlabel' => 'Withdrawn By'],
                ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('saved-filters')
            ->where('module', 'material_issues')
            ->where('is_default', true)
            ->whereNull('organization_id')
            ->delete();
    }
};
