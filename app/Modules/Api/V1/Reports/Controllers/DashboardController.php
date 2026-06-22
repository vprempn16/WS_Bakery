<?php

namespace App\Modules\Api\V1\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\BranchSales\Models\BranchDailyReport;
use App\Modules\Api\V1\BranchSales\Models\BranchDailyReportItem;
use App\Modules\Api\V1\ProductionBatch\Models\ProductionBatch;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $orgId = $request->user()->organization_id;
        $today = Carbon::today();
        
        // 1. KPIs
        $salesToday = BranchDailyReport::where('organization_id', $orgId)
            ->whereDate('report_date', $today)
            ->sum('total_revenue');

        $wasteToday = BranchDailyReport::where('organization_id', $orgId)
            ->whereDate('report_date', $today)
            ->sum('total_waste_amount');

        $productionToday = ProductionBatch::where('organization_id', $orgId)
            ->whereDate('production_date', $today)
            ->count();

        // 2. Sales Trend (Last 7 Days)
        $sevenDaysAgo = Carbon::today()->subDays(6);
        $salesTrend = BranchDailyReport::where('organization_id', $orgId)
            ->whereDate('report_date', '>=', $sevenDaysAgo)
            ->select(DB::raw('DATE(report_date) as date'), DB::raw('SUM(total_revenue) as revenue'))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        // Fill in missing days with 0
        $trendData = [];
        for ($i = 0; $i < 7; $i++) {
            $dateStr = $sevenDaysAgo->copy()->addDays($i)->format('Y-m-d');
            $found = $salesTrend->firstWhere('date', $dateStr);
            $trendData[] = [
                'date' => $dateStr,
                'revenue' => $found ? (float) $found->revenue : 0
            ];
        }

        // 3. Top Selling Products (Last 30 Days)
        $thirtyDaysAgo = Carbon::today()->subDays(30);
        $topProducts = BranchDailyReportItem::join('branch_daily_reports', 'branch_daily_reports.id', '=', 'branch_daily_report_items.branch_daily_report_id')
            ->join('products', 'products.id', '=', 'branch_daily_report_items.product_id')
            ->where('branch_daily_reports.organization_id', $orgId)
            ->whereDate('branch_daily_reports.report_date', '>=', $thirtyDaysAgo)
            ->select('products.name', DB::raw('SUM(branch_daily_report_items.quantity_sold) as total_sold'))
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        return $this->success([
            'kpis' => [
                'salesToday' => (float) $salesToday,
                'wasteToday' => (float) $wasteToday,
                'productionBatchesToday' => $productionToday,
            ],
            'salesTrend7Days' => $trendData,
            'topProducts30Days' => $topProducts->map(function ($item) {
                return [
                    'name' => $item->name,
                    'totalSold' => (float) $item->total_sold
                ];
            })
        ], 'Dashboard summary fetched successfully.');
    }
}
