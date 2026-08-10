<?php

namespace App\Modules\Api\V1\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\Billing\Models\Billing;
use App\Modules\Api\V1\Billing\Models\BillingItem;
use App\Modules\Api\V1\BranchSales\Models\BranchDailyReport;
use App\Modules\Api\V1\ProductionBatch\Models\ProductionBatch;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $orgId = $request->user()->organization_id;
        $today = Carbon::today();

        // POS Billing is the sales source of truth
        $salesToday = (float) Billing::where('organization_id', $orgId)
            ->whereDate('billing_date', $today)
            ->whereRaw('LOWER(payment_status) = ?', ['paid'])
            ->sum('grand_total');

        $wasteToday = (float) BranchDailyReport::where('organization_id', $orgId)
            ->whereDate('report_date', $today)
            ->sum('total_waste_amount');

        $productionToday = ProductionBatch::where('organization_id', $orgId)
            ->whereDate('production_date', $today)
            ->where(function ($q) {
                $q->whereNull('status')->orWhereRaw('LOWER(status) != ?', ['cancelled']);
            })
            ->count();

        $sevenDaysAgo = Carbon::today()->subDays(6);
        $salesTrend = Billing::where('organization_id', $orgId)
            ->whereDate('billing_date', '>=', $sevenDaysAgo)
            ->whereRaw('LOWER(payment_status) = ?', ['paid'])
            ->select(DB::raw('DATE(billing_date) as date'), DB::raw('SUM(grand_total) as revenue'))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        $trendData = [];
        for ($i = 0; $i < 7; $i++) {
            $dateStr = $sevenDaysAgo->copy()->addDays($i)->format('Y-m-d');
            $found = $salesTrend->firstWhere('date', $dateStr);
            $trendData[] = [
                'date' => $dateStr,
                'revenue' => $found ? (float) $found->revenue : 0,
            ];
        }

        $thirtyDaysAgo = Carbon::today()->subDays(30);
        $topProducts = BillingItem::query()
            ->join('billings', 'billings.id', '=', 'billing_items.billing_id')
            ->join('products', 'products.id', '=', 'billing_items.product_id')
            ->where('billings.organization_id', $orgId)
            ->whereRaw('LOWER(billings.payment_status) = ?', ['paid'])
            ->whereDate('billings.billing_date', '>=', $thirtyDaysAgo)
            ->select('products.name', DB::raw('SUM(billing_items.quantity) as total_sold'))
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        return $this->success([
            'kpis' => [
                'salesToday' => $salesToday,
                'wasteToday' => $wasteToday,
                'productionBatchesToday' => $productionToday,
            ],
            'salesTrend7Days' => $trendData,
            'topProducts30Days' => $topProducts->map(function ($item) {
                return [
                    'name' => $item->name,
                    'totalSold' => (float) $item->total_sold,
                ];
            }),
        ], 'Dashboard summary fetched successfully.');
    }
}
