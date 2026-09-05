<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * createdAt on Production Plan / Material Withdraw was displaytype 3,
 * so lists showed a system "Created At" timestamp (with time) next to the
 * real business date (planDate / issueDate). Hide it as displaytype 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['ProductionPlan', 'MaterialIssue'] as $module) {
            DB::table('crm_fields')
                ->where('modulename', $module)
                ->where(function ($q) {
                    $q->where('apifieldname', 'createdAt')
                        ->orWhere('fieldname', 'created_at')
                        ->orWhere('fieldname', 'createdAt');
                })
                ->where('deleted', 0)
                ->update(['displaytype' => 2]);
        }

        // Drop from default / custom saved-filter header_details so stale columns disappear.
        foreach (['production_plans', 'material_issues'] as $module) {
            $filters = DB::table('saved-filters')
                ->where('module', $module)
                ->whereNotNull('header_details')
                ->get(['id', 'header_details']);

            foreach ($filters as $filter) {
                $headers = is_string($filter->header_details)
                    ? json_decode($filter->header_details, true)
                    : $filter->header_details;
                if (! is_array($headers)) {
                    continue;
                }
                $next = array_values(array_filter(
                    $headers,
                    fn ($h) => ($h['fieldname'] ?? '') !== 'createdAt'
                ));
                if (count($next) === count($headers)) {
                    continue;
                }
                DB::table('saved-filters')
                    ->where('id', $filter->id)
                    ->update(['header_details' => json_encode($next)]);
            }
        }
    }

    public function down(): void
    {
        foreach (['ProductionPlan', 'MaterialIssue'] as $module) {
            DB::table('crm_fields')
                ->where('modulename', $module)
                ->where(function ($q) {
                    $q->where('apifieldname', 'createdAt')
                        ->orWhere('fieldname', 'created_at')
                        ->orWhere('fieldname', 'createdAt');
                })
                ->where('deleted', 0)
                ->update(['displaytype' => 3]);
        }
    }
};
