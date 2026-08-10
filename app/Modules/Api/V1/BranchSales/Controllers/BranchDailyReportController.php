<?php

namespace App\Modules\Api\V1\BranchSales\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\BranchSales\Models\BranchDailyReport;
use App\Modules\Api\V1\BranchSales\Requests\StoreBranchDailyReportRequest;
use App\Modules\Api\V1\BranchSales\Resources\BranchDailyReportResource;
use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use App\Modules\Api\V1\Product\Models\Product;
use App\Services\BranchAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BranchDailyReportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $permissionService = new \App\Services\PermissionService($user);
        if ($deny = $permissionService->denyMessage('BranchDailyReport', 'view')) {
            return $this->error($deny, null, null, null, 403);
        }

        $orgId = $user->organization_id;
        $perPage = \App\Support\ApiPagination::perPage($request);

        $query = BranchDailyReport::with(['branch', 'items.product'])
            ->where('organization_id', $orgId);

        if ($user && !$user->isFullAdmin() && $user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }

        if ($request->has('savedFilterId')) {
            $savedFilter = \App\Modules\Api\V1\SavedFilter\Models\SavedFilter::where('organization_id', $orgId)
                ->findOrFail($request->query('savedFilterId'));
            \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'branch_daily_reports', $savedFilter->rules);
        }

        if ($request->has('rules')) {
            $rules = $request->input('rules');
            if (is_string($rules)) {
                $rules = json_decode($rules, true);
            }
            if (is_array($rules)) {
                \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'branch_daily_reports', $rules);
            }
        }

        $reports = $query->orderBy('report_date', 'desc')->paginate($perPage);

        $fieldList = \App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig::getApiFieldsForView('BranchDailyReport', 'DetailView');

        return $this->paginated(BranchDailyReportResource::collection($reports)->resource, $fieldList);
    }

    public function store(StoreBranchDailyReportRequest $request)
    {
        $values = $request->input('data.values');
        $orgId = $request->user()->organization_id;
        $branchId = $values['branchId'];
        $reportDate = $values['reportDate'];

        try {
            BranchAccess::assertCanAccessBranch($request->user(), (string) $branchId);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
        }

        $existingReport = BranchDailyReport::where('organization_id', $orgId)
            ->where('branch_id', $branchId)
            ->where('report_date', $reportDate)
            ->first();

        if ($existingReport) {
            return $this->error('A report for this branch and date already exists.', null, null, null, 400);
        }

        try {
            DB::beginTransaction();

            $totalRevenue = 0;
            $totalWasteAmount = 0;
            $itemsData = [];

            // POS Billing is source of truth for sold qty stock.
            // Daily report only deducts waste/returns (quantityReturned).
            foreach ($values['items'] as $item) {
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

                    if (!$branchStock || (float) $branchStock->current_stock < $qtyReturned) {
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

            if (empty($itemsData)) {
                throw new \RuntimeException('No valid items to report.');
            }

            $report = BranchDailyReport::create([
                'organization_id' => $orgId,
                'branch_id' => $branchId,
                'report_date' => $reportDate,
                'total_revenue' => $totalRevenue,
                'total_waste_amount' => $totalWasteAmount,
                'status' => 'submitted',
                'notes' => $values['notes'] ?? null,
                'created_by' => $request->user()->id,
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
            return $this->error('Failed to submit report: ' . $e->getMessage(), null, null, null, 500);
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
            $fieldList = \App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig::getApiFieldsForView('BranchDailyReport', 'DetailView');

            return $this->success([
                'fields' => $fieldList,
                'values' => $resource->toArray(request()),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Daily Report not found.', null, null, null, 404);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
        }
    }
}
