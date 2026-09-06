<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Re-seed 3/4 month shelf-life options if the earlier migration used wrong modulename.
 */
return new class extends Migration
{
    public function up(): void
    {
        $fieldIds = DB::table('crm_fields')
            ->where('modulename', 'Product')
            ->where(function ($q) {
                $q->where('fieldname', 'shelfLife')
                    ->orWhere('apifieldname', 'shelfLife')
                    ->orWhere('fieldname', 'shelf_life');
            })
            ->where('deleted', 0)
            ->pluck('id');

        foreach ($fieldIds as $fieldId) {
            foreach ([
                ['value' => '2160', 'label' => '3 Months'],
                ['value' => '2880', 'label' => '4 Months'],
            ] as $i => $opt) {
                $exists = DB::table('picklist_values')
                    ->where('field_id', $fieldId)
                    ->where('value', $opt['value'])
                    ->exists();
                if ($exists) {
                    continue;
                }
                $maxSort = (int) DB::table('picklist_values')->where('field_id', $fieldId)->max('sort_order');
                DB::table('picklist_values')->insert([
                    'id' => (string) Str::uuid(),
                    'field_id' => $fieldId,
                    'label' => $opt['label'],
                    'value' => $opt['value'],
                    'sort_order' => $maxSort + $i + 1,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Keep options; safe no-op
    }
};
