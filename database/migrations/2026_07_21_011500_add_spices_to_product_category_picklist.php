<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $fieldId = DB::table('crm_fields')
            ->where('modulename', 'Product')
            ->where(function ($q) {
                $q->where('apifieldname', 'category')
                    ->orWhere('fieldname', 'category');
            })
            ->where('deleted', 0)
            ->value('id');

        if (!$fieldId) {
            return;
        }

        $exists = DB::table('picklist_values')
            ->where('field_id', $fieldId)
            ->where('value', 'spices')
            ->exists();

        if ($exists) {
            return;
        }

        $maxOrder = (int) DB::table('picklist_values')
            ->where('field_id', $fieldId)
            ->max('sort_order');

        DB::table('picklist_values')->insert([
            'id' => (string) Str::uuid(),
            'field_id' => $fieldId,
            'value' => 'spices',
            'label' => 'Spices',
            'status' => 1,
            'sort_order' => $maxOrder + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $fieldId = DB::table('crm_fields')
            ->where('modulename', 'Product')
            ->where(function ($q) {
                $q->where('apifieldname', 'category')
                    ->orWhere('fieldname', 'category');
            })
            ->where('deleted', 0)
            ->value('id');

        if (!$fieldId) {
            return;
        }

        DB::table('picklist_values')
            ->where('field_id', $fieldId)
            ->where('value', 'spices')
            ->delete();
    }
};
