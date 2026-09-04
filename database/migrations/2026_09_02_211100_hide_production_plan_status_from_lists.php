<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_fields')) {
            DB::table('crm_fields')
                ->where('modulename', 'ProductionPlan')
                ->where(function ($q) {
                    $q->where('apifieldname', 'status')
                        ->orWhere('fieldname', 'status');
                })
                ->where('deleted', 0)
                ->update([
                    'displaytype' => 2,
                    'updated_at' => now(),
                ]);
        }

        if (! Schema::hasTable('saved-filters')) {
            return;
        }

        $filters = DB::table('saved-filters')
            ->where('module', 'production_plans')
            ->get();

        foreach ($filters as $filter) {
            $headers = json_decode($filter->header_details ?? '[]', true);
            if (! is_array($headers)) {
                continue;
            }

            $updated = array_values(array_filter($headers, function ($col) {
                return ($col['fieldname'] ?? null) !== 'status';
            }));

            if ($updated === $headers) {
                continue;
            }

            DB::table('saved-filters')
                ->where('id', $filter->id)
                ->update(['header_details' => json_encode($updated)]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_fields')) {
            DB::table('crm_fields')
                ->where('modulename', 'ProductionPlan')
                ->where(function ($q) {
                    $q->where('apifieldname', 'status')
                        ->orWhere('fieldname', 'status');
                })
                ->where('deleted', 0)
                ->update([
                    'displaytype' => 1,
                    'updated_at' => now(),
                ]);
        }
    }
};
