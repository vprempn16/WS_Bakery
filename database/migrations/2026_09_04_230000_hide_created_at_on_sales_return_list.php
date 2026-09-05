<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * createdAt on Sales Return was displaytype 3, so lists showed a system
 * "Created At" timestamp next to the real business date (returnDate).
 * Hide it as displaytype 2 (same as Production Plan / Material Withdraw).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('crm_fields')
            ->where('modulename', 'SalesReturn')
            ->where(function ($q) {
                $q->where('apifieldname', 'createdAt')
                    ->orWhere('fieldname', 'created_at')
                    ->orWhere('fieldname', 'createdAt');
            })
            ->where('deleted', 0)
            ->update(['displaytype' => 2]);

        $filters = DB::table('saved-filters')
            ->where('module', 'sales_returns')
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

    public function down(): void
    {
        DB::table('crm_fields')
            ->where('modulename', 'SalesReturn')
            ->where(function ($q) {
                $q->where('apifieldname', 'createdAt')
                    ->orWhere('fieldname', 'created_at')
                    ->orWhere('fieldname', 'createdAt');
            })
            ->where('deleted', 0)
            ->update(['displaytype' => 3]);
    }
};
