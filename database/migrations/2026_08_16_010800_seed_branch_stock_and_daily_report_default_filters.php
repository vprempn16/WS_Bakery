<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * BranchStock / BranchDailyReport lists require a default "All" filter
     * so the frontend DataProvider can load headers + records.
     */
    public function up(): void
    {
        $modules = [
            'branch_stocks' => [
                ['fieldname' => 'branchId', 'fieldlabel' => 'Branch'],
                ['fieldname' => 'productId', 'fieldlabel' => 'Product'],
                ['fieldname' => 'currentStock', 'fieldlabel' => 'Current Stock'],
                ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At'],
            ],
            'branch_daily_reports' => [
                ['fieldname' => 'branchId', 'fieldlabel' => 'Branch'],
                ['fieldname' => 'reportDate', 'fieldlabel' => 'Report Date'],
                ['fieldname' => 'totalRevenue', 'fieldlabel' => 'Total Revenue'],
                ['fieldname' => 'totalWasteAmount', 'fieldlabel' => 'Total Waste Amount'],
                ['fieldname' => 'status', 'fieldlabel' => 'Status'],
                ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At'],
            ],
        ];

        $now = now();
        foreach ($modules as $moduleName => $fields) {
            $exists = DB::table('saved-filters')
                ->where('module', $moduleName)
                ->where('is_default', true)
                ->whereNull('organization_id')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('saved-filters')->insert([
                'id' => Str::uuid()->toString(),
                'organization_id' => null,
                'user_id' => null,
                'name' => 'All',
                'module' => $moduleName,
                'rules' => json_encode([]),
                'is_public' => true,
                'is_default' => true,
                'header_details' => json_encode($fields),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('saved-filters')
            ->whereIn('module', ['branch_stocks', 'branch_daily_reports'])
            ->where('is_default', true)
            ->whereNull('organization_id')
            ->delete();
    }
};
