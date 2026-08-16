<?php

namespace App\Modules\Api\V1\BranchSales\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\Billing\Models\Billing;
use App\Modules\Api\V1\Billing\Models\BillingItem;
use App\Modules\Api\V1\BranchSales\Models\BranchDailyReport;
use App\Modules\Api\V1\BranchSales\Requests\StoreBranchDailyReportRequest;
use App\Modules\Api\V1\BranchSales\Resources\BranchDailyReportResource;
use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\SavedFilter\Models\SavedFilter;
use App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig;
use App\Modules\Api\V1\SavedFilter\Services\QueryFilterService;
use App\Services\BranchAccess;
use App\Services\PermissionService;
use App\Support\ApiPagination;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BranchDailyReportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $permissionService = new PermissionService($user);
        if ($deny = $permissionService->denyMessage('BranchDailyReport', 'view')) {
            return $this->error($deny, null, null, null, 403);
        }

        $orgId = $user->organization_id;
        $perPage = ApiPagination::perPage($request);

        $query = BranchDailyReport::with(['branch', 'items.product'])
            ->where('organization_id', $orgId);

        try {
            BranchAccess::applyListBranchScope($query, $request, $user);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
        }

        if ($request->has('savedFilterId')) {
            $savedFilter = SavedFilter::where('organization_id', $orgId)
                ->findOrFail($request->query('savedFilterId'));
            QueryFilterService::apply($query, 'branch_daily_reports', $savedFilter->rules);
        }

        if ($request->has('rules')) {
            $rules = $request->input('rules');
            if (is_string($rules)) {
                $rules = json_decode($rules, true);
            }
            if (is_array($rules)) {
                QueryFilterService::apply($query, 'branch_daily_reports', $rules);
            }
        }

        if ($request->filled('dateFrom')) {
            $query->whereDate('report_date', '>=', $request->query('dateFrom'));
        }
        if ($request->filled('dateTo')) {
            $query->whereDate('report_date', '<=', $request->query('dateTo'));
        }

        $reports = $query->orderBy('report_date', 'desc')->paginate($perPage);

        $fieldList = ModuleFieldConfig::getApiFieldsForView('BranchDailyReport', 'DetailView');

        return $this->paginated(BranchDailyReportResource::collection($reports)->resource, $fieldList);
    }

    public function store(StoreBranchDailyReportRequest $request)
    {
        $values = $request->input('data.values');
        $user = $request->user();
        $permissionService = new PermissionService($user);
        if ($deny = $permissionService->denyMessage('BranchDailyReport', 'create')) {
            return $this->error($deny, null, null, null, 403);
        }

        $orgId = $user->organization_id;
        // Staff are locked to user.branch_id; full admins use X-Branch-Id.
        $branchId = BranchAccess::resolveBranchIdFromRequest($request, $user)
            ?: ($values['branchId'] ?? null);
        $reportDate = $values['reportDate'];

        if (! $branchId) {
            return $this->error('Select an active branch before generating the report.', null, null, null, 422);
        }

        try {
            BranchAccess::assertCanAccessBranch($user, (string) $branchId);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
        }

        $submittedItems = $values['items'] ?? null;

        $existingReport = BranchDailyReport::where('organization_id', $orgId)
            ->where('branch_id', $branchId)
            ->whereDate('report_date', $reportDate)
            ->first();

        if ($existingReport && $submittedItems !== null) {
            return $this->error('A report for this branch and date already exists.', null, null, null, 400);
        }

        try {
            DB::beginTransaction();

            if ($existingReport) {
                $report = BranchDailyReport::where('id', $existingReport->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $preservedWaste = [];
                foreach ($report->items()->get() as $item) {
                    $qtyReturned = (float) $item->quantity_returned;
                    $subtotalWaste = (float) $item->subtotal_waste;
                    if ($qtyReturned <= 0 && $subtotalWaste <= 0) {
                        continue;
                    }
                    $preservedWaste[$item->product_id] = [
                        'quantity_returned' => $qtyReturned,
                        'subtotal_waste' => $subtotalWaste,
                    ];
                }

                $posItems = $this->buildPosSoldItems($orgId, $branchId, $reportDate);
                $itemsData = $this->mergePosItemsWithPreservedWaste($posItems, $preservedWaste);

                // Header revenue = paid bill grand totals (includes discount/tax), not raw line sum.
                $totalRevenue = $this->sumPaidPosGrandTotal($orgId, $branchId, $reportDate);
                $totalWasteAmount = 0;
                foreach ($itemsData as $data) {
                    $totalWasteAmount += (float) $data['subtotal_waste'];
                }

                $report->total_revenue = $totalRevenue;
                $report->total_waste_amount = $totalWasteAmount;
                if (array_key_exists('notes', $values)) {
                    $report->notes = $values['notes'];
                }
                $report->save();

                $report->items()->delete();
                foreach ($itemsData as $data) {
                    $report->items()->create($data);
                }

                DB::commit();
                $report->load(['branch', 'items.product']);

                return $this->success(
                    new BranchDailyReportResource($report),
                    'Branch Daily Report regenerated from latest POS sales.',
                    200
                );
            }

            $totalRevenue = 0;
            $totalWasteAmount = 0;
            $itemsData = [];

            // POS Billing is the source of truth when the generic report form does
            // not submit manual lines. Paid sales have already deducted stock.
            if ($submittedItems === null) {
                $itemsData = $this->buildPosSoldItems($orgId, $branchId, $reportDate);
                $totalRevenue = $this->sumPaidPosGrandTotal($orgId, $branchId, $reportDate);
            }

            // Backward-compatible manual lines are still supported. Only reported
            // waste/returns deduct branch stock; sold stock is handled by POS.
            foreach ($submittedItems ?? [] as $item) {
                $productId = $item['productId'];
                $qtySold = (float) $item['quantitySold'];
                $qtyReturned = (float) $item['quantityReturned'];

                if ($qtySold <= 0 && $qtyReturned <= 0) {
                    continue;
                }

                $product = Product::where('organization_id', $orgId)->findOrFail($productId);
                $unitPrice = (float) $product->price;

                $subtotalRevenue = $qtySold * $unitPrice;
                $subtotalWaste = $qtyReturned * $unitPrice;

                $totalRevenue += $subtotalRevenue;
                $totalWasteAmount += $subtotalWaste;

                // Deduct only waste/returns from branch stock
                if ($qtyReturned > 0) {
                    $branchStock = BranchStock::where('organization_id', $orgId)
                        ->where('branch_id', $branchId)
                        ->where('product_id', $productId)
                        ->lockForUpdate()
                        ->first();

                    if (! $branchStock || (float) $branchStock->current_stock < $qtyReturned) {
                        throw new \RuntimeException("Insufficient stock at branch for waste/return of Product ID: {$productId}");
                    }

                    $branchStock->current_stock = (float) $branchStock->current_stock - $qtyReturned;
                    $branchStock->save();
                }

                $itemsData[] = [
                    'product_id' => $productId,
                    'quantity_sold' => $qtySold,
                    'quantity_returned' => $qtyReturned,
                    'unit_price' => $unitPrice,
                    'subtotal_revenue' => $subtotalRevenue,
                    'subtotal_waste' => $subtotalWaste,
                ];
            }

            $report = BranchDailyReport::create([
                'organization_id' => $orgId,
                'branch_id' => $branchId,
                'report_date' => $reportDate,
                'total_revenue' => $totalRevenue,
                'total_waste_amount' => $totalWasteAmount,
                'status' => 'submitted',
                'notes' => $values['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            foreach ($itemsData as $data) {
                $report->items()->create($data);
            }

            DB::commit();
            $report->load(['branch', 'items.product']);

            return $this->success(new BranchDailyReportResource($report), 'Branch Daily Report submitted successfully.', 201);
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), null, null, null, 400);
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('BranchDailyReport store failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->error('Failed to submit report: '.$e->getMessage(), null, null, null, 500);
        }
    }


    public function show($id, Request $request)
    {
        try {
            $orgId = $request->user()->organization_id;
            $report = BranchDailyReport::with(['branch', 'items.product'])
                ->where('organization_id', $orgId)
                ->findOrFail($id);

            BranchAccess::assertCanAccessBranch($request->user(), (string) $report->branch_id);

            $resource = new BranchDailyReportResource($report);
            $fieldList = ModuleFieldConfig::getApiFieldsForView('BranchDailyReport', 'DetailView');

            return $this->success([
                'fields' => $fieldList,
                'values' => $resource->toArray(request()),
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->error('Daily Report not found.', null, null, null, 404);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
        }
    }

    /**
     * Cash collected from paid POS bills for the branch/date (after discount, with tax).
     */
    private function sumPaidPosGrandTotal(string $orgId, string $branchId, string $reportDate): float
    {
        return (float) Billing::query()
            ->where('organization_id', $orgId)
            ->where('branch_id', $branchId)
            ->whereDate('billing_date', $reportDate)
            ->whereRaw('LOWER(payment_status) = ?', ['paid'])
            ->sum('grand_total');
    }

    /**
     * Aggregate paid POS billing lines into daily-report item rows (sold only).
     *
     * @return array<int, array{product_id: mixed, quantity_sold: float, quantity_returned: float, unit_price: float, subtotal_revenue: float, subtotal_waste: float}>
     */
    private function buildPosSoldItems(string $orgId, string $branchId, string $reportDate): array
    {
        $sales = BillingItem::query()
            ->join('billings', 'billings.id', '=', 'billing_items.billing_id')
            ->where('billings.organization_id', $orgId)
            ->where('billings.branch_id', $branchId)
            ->whereDate('billings.billing_date', $reportDate)
            ->whereRaw('LOWER(billings.payment_status) = ?', ['paid'])
            ->selectRaw(
                'billing_items.product_id, SUM(billing_items.quantity) as quantity_sold, '.
                'SUM(billing_items.total_price) as subtotal_revenue'
            )
            ->groupBy('billing_items.product_id')
            ->get();

        $itemsData = [];
        foreach ($sales as $sale) {
            $quantitySold = (float) $sale->quantity_sold;
            $subtotalRevenue = (float) $sale->subtotal_revenue;
            $unitPrice = $quantitySold > 0 ? $subtotalRevenue / $quantitySold : 0;
            $itemsData[] = [
                'product_id' => $sale->product_id,
                'quantity_sold' => $quantitySold,
                'quantity_returned' => 0,
                'unit_price' => $unitPrice,
                'subtotal_revenue' => $subtotalRevenue,
                'subtotal_waste' => 0,
            ];
        }

        return $itemsData;
    }

    /**
     * Merge POS sold rows with preserved waste/returns from a prior report snapshot.
     *
     * @param  array<int, array{product_id: mixed, quantity_sold: float, quantity_returned: float, unit_price: float, subtotal_revenue: float, subtotal_waste: float}>  $posItems
     * @param  array<string, array{quantity_returned: float, subtotal_waste: float}>  $preservedWaste
     * @return array<int, array{product_id: mixed, quantity_sold: float, quantity_returned: float, unit_price: float, subtotal_revenue: float, subtotal_waste: float}>
     */
    private function mergePosItemsWithPreservedWaste(array $posItems, array $preservedWaste): array
    {
        $itemsByProduct = [];

        foreach ($posItems as $item) {
            $productId = $item['product_id'];
            $waste = $preservedWaste[$productId] ?? null;
            $itemsByProduct[$productId] = [
                'product_id' => $productId,
                'quantity_sold' => $item['quantity_sold'],
                'quantity_returned' => $waste['quantity_returned'] ?? 0,
                'unit_price' => $item['unit_price'],
                'subtotal_revenue' => $item['subtotal_revenue'],
                'subtotal_waste' => $waste['subtotal_waste'] ?? 0,
            ];
            unset($preservedWaste[$productId]);
        }

        // Keep waste-only products that had returns but no paid sales that day.
        foreach ($preservedWaste as $productId => $waste) {
            $itemsByProduct[$productId] = [
                'product_id' => $productId,
                'quantity_sold' => 0,
                'quantity_returned' => $waste['quantity_returned'],
                'unit_price' => $waste['quantity_returned'] > 0
                    ? $waste['subtotal_waste'] / $waste['quantity_returned']
                    : 0,
                'subtotal_revenue' => 0,
                'subtotal_waste' => $waste['subtotal_waste'],
            ];
        }

        return array_values($itemsByProduct);
    }
}
