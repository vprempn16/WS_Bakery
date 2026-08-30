<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('portal_modules')) {
            DB::table('portal_modules')
                ->where('modulename', 'MaterialIssue')
                ->update(['modulelabel' => 'Material Withdrawal']);

            DB::table('portal_modules')
                ->where('modulename', 'MaterialIssueItem')
                ->update(['modulelabel' => 'Material Withdrawal Item']);
        }

        if (Schema::hasTable('saved-filters')) {
            $filters = DB::table('saved-filters')
                ->where('module', 'material_issues')
                ->where('is_default', true)
                ->get();

            foreach ($filters as $filter) {
                $headers = json_decode($filter->header_details ?? '[]', true);
                if (! is_array($headers)) {
                    continue;
                }

                $labelMap = [
                    'issueNumber' => 'Withdrawal Number',
                    'issueDate' => 'Withdrawal Date',
                    'createdBy' => 'Withdrawn By',
                ];

                $updated = array_map(function ($col) use ($labelMap) {
                    $name = $col['fieldname'] ?? null;
                    if ($name && isset($labelMap[$name])) {
                        $col['fieldlabel'] = $labelMap[$name];
                    }

                    return $col;
                }, $headers);

                DB::table('saved-filters')
                    ->where('id', $filter->id)
                    ->update(['header_details' => json_encode($updated)]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('portal_modules')) {
            DB::table('portal_modules')
                ->where('modulename', 'MaterialIssue')
                ->update(['modulelabel' => 'Material Issue']);

            DB::table('portal_modules')
                ->where('modulename', 'MaterialIssueItem')
                ->update(['modulelabel' => 'Material Issue Item']);
        }
    }
};
