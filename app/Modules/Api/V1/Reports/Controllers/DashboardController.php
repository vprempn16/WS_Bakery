<?php

namespace App\Modules\Api\V1\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\Billing\Models\Billing;
use App\Modules\Api\V1\Billing\Models\BillingItem;
use App\Modules\Api\V1\Branch\Models\Branch;
use App\Modules\Api\V1\BranchSales\Models\BranchDailyReport;
use App\Modules\Api\V1\ProductionBatch\Models\ProductionBatch;
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
        $today = Carbon::today();

        $branchId = $request->header('X-Branch-Id') ?: $request->query('branch_id') ?: $request->query('branchId');
        if ($branchId) {
            try {
                BranchAccess::assertCanAccessBranch($user, (string) $branchId);
            } catch (\RuntimeException $e) {
                return $this->error($e->getMessage(), null, null, null, 403);
            }

            // Extra org check (BranchAccess already scopes admin to org branches)
            $exists = Branch::where('organization_id', $orgId)->where('id', $branchId)->exists();
            if (! $exists) {
                return $this->error('Branch not found in this organization.', null, null, null, 403);
            }
        }

        // POS Billing is the sales source of truth (optionally branch-scoped)
        $salesQuery = Billing::where('organization_id', $orgId)
            ->whereDate('billing_date', $today)
            ->whereRaw('LOWER(payment_status) = ?', ['paid']);
        if ($branchId) {
            $salesQuery->where('branch_id', $branchId);
        }
        $salesToday = (float) $salesQuery->sum('grand_total');

        $wasteQuery = BranchDailyReport::where('organization_id', $orgId)
            ->whereDate('report_date', $today);
        if ($branchId) {
            $wasteQuery->where('branch_id', $branchId);
        }
        $wasteToday = (float) $wasteQuery->sum('total_waste_amount');

        // Production is warehouse/org-level (not per retail branch)
        $productionToday = ProductionBatch::where('organization_id', $orgId)
            ->whereDate('production_date', $today)
            ->where(function ($q) {
                $q->whereNull('status')->orWhereRaw('LOWER(status) != ?', ['cancelled']);
            })
            ->count();

        $sevenDaysAgo = Carbon::today()->subDays(6);
        $trendQuery = Billing::where('organization_id', $orgId)
            ->whereDate('billing_date', '>=', $sevenDaysAgo)
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
        for ($i = 0; $i < 7; $i++) {
            $dateStr = $sevenDaysAgo->copy()->addDays($i)->format('Y-m-d');
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
            'kpis' => [
                'salesToday' => $salesToday,
                'wasteToday' => $wasteToday,
                'productionBatchesToday' => $productionToday,
            ],
            'branchId' => $branchId ? (string) $branchId : null,
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
