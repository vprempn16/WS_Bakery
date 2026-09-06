<?php

namespace App\Modules\Api\V1\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\Billing\Models\Billing;
use App\Modules\Api\V1\Billing\Models\BillingItem;
use App\Modules\Api\V1\Branch\Models\Branch;
use App\Modules\Api\V1\ProductionBatch\Models\ProductionBatch;
use App\Modules\Api\V1\SalesReturn\Models\SalesReturn;
use App\Services\BranchAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $user = $request->user();
        if (! $user || ! method_exists($user, 'isFullAdmin') || ! $user->isFullAdmin()) {
            return $this->error('Admin access required.', null, null, null, 403);
        }

        $orgId = $user->organization_id;
        $period = strtolower((string) $request->query('period', 'day'));
        if (! in_array($period, ['day', 'week', 'month'], true)) {
            $period = 'day';
        }

        $today = Carbon::today();
        if ($period === 'week') {
            $rangeStart = $today->copy()->subDays(6);
            $trendDays = 7;
        } elseif ($period === 'month') {
            $rangeStart = $today->copy()->subDays(29);
            $trendDays = 30;
        } else {
            $rangeStart = $today->copy();
            $trendDays = 1;
        }

        $branchId = BranchAccess::resolveBranchIdFromRequest($request, $user);
        if ($branchId) {
            try {
                BranchAccess::assertCanAccessBranch($user, (string) $branchId);
            } catch (\RuntimeException $e) {
                return $this->error($e->getMessage(), null, null, null, 403);
            }

            $exists = Branch::where('organization_id', $orgId)->where('id', $branchId)->exists();
            if (! $exists) {
                return $this->error('Branch not found in this organization.', null, null, null, 403);
            }
        }

        $salesQuery = Billing::where('organization_id', $orgId)
            ->whereDate('billing_date', '>=', $rangeStart)
            ->whereDate('billing_date', '<=', $today)
            ->whereRaw('LOWER(payment_status) = ?', ['paid']);
        if ($branchId) {
            $salesQuery->where('branch_id', $branchId);
        }
        $salesTotal = (float) $salesQuery->sum('grand_total');

        // Returns KPI = SalesReturn loss value (not BranchDailyReport waste, which is report-only / often 0).
        $returnsQuery = SalesReturn::where('organization_id', $orgId)
            ->whereDate('return_date', '>=', $rangeStart)
            ->whereDate('return_date', '<=', $today);
        if ($branchId) {
            $returnsQuery->where('branch_id', $branchId);
        }
        $returnsTotal = (float) $returnsQuery->sum('total_return_value');

        $productionQuery = ProductionBatch::where('organization_id', $orgId)
            ->whereDate('production_date', '>=', $rangeStart)
            ->whereDate('production_date', '<=', $today)
            ->where(function ($q) {
                $q->whereNull('status')->orWhereRaw('LOWER(status) != ?', ['cancelled']);
            });
        $productionCount = $productionQuery->count();

        $trendQuery = Billing::where('organization_id', $orgId)
            ->whereDate('billing_date', '>=', $rangeStart)
            ->whereDate('billing_date', '<=', $today)
            ->whereRaw('LOWER(payment_status) = ?', ['paid']);
        if ($branchId) {
            $trendQuery->where('branch_id', $branchId);
        }
        $salesTrend = $trendQuery
            ->select(DB::raw('DATE(billing_date) as date'), DB::raw('SUM(grand_total) as revenue'))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        $trendData = [];
        for ($i = 0; $i < $trendDays; $i++) {
            $dateStr = $rangeStart->copy()->addDays($i)->format('Y-m-d');
            $found = $salesTrend->firstWhere('date', $dateStr);
            $trendData[] = [
                'date' => $dateStr,
                'revenue' => $found ? (float) $found->revenue : 0,
            ];
        }

        $thirtyDaysAgo = Carbon::today()->subDays(30);
        $topQuery = BillingItem::query()
            ->join('billings', 'billings.id', '=', 'billing_items.billing_id')
            ->join('products', 'products.id', '=', 'billing_items.product_id')
            ->where('billings.organization_id', $orgId)
            ->whereRaw('LOWER(billings.payment_status) = ?', ['paid'])
            ->whereDate('billings.billing_date', '>=', $thirtyDaysAgo);
        if ($branchId) {
            $topQuery->where('billings.branch_id', $branchId);
        }
        $topProducts = $topQuery
            ->select('products.name', DB::raw('SUM(billing_items.quantity) as total_sold'))
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        return $this->success([
            'period' => $period,
            'kpis' => [
                'salesTotal' => $salesTotal,
                'salesToday' => $salesTotal,
                'returnsTotal' => $returnsTotal,
                'wasteToday' => $returnsTotal,
                'returnsToday' => $returnsTotal,
                'productionBatches' => $productionCount,
                'productionBatchesToday' => $productionCount,
            ],
            'branchId' => $branchId ? (string) $branchId : null,
            'salesTrend' => $trendData,
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
