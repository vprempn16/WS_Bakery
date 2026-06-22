<?php

namespace App\Modules\Api\V1\BranchSales\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\BranchSales\Models\BranchDailyReport;
use App\Modules\Api\V1\BranchSales\Models\BranchDailyReportItem;
use App\Modules\Api\V1\BranchSales\Requests\StoreBranchDailyReportRequest;
use App\Modules\Api\V1\BranchSales\Resources\BranchDailyReportResource;
use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use App\Modules\Api\V1\Product\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BranchDailyReportController extends Controller
{
    public function index(Request $request)
    {
        $orgId = $request->user()->organization_id;
        $perPage = $request->query('per_page', 20);

        $query = BranchDailyReport::with(['branch', 'items.product'])
            ->where('organization_id', $orgId);

        // Apply saved filter if provided
        if ($request->has('savedFilterId')) {
            $savedFilter = \App\Modules\Api\V1\SavedFilter\Models\SavedFilter::where('organization_id', $orgId)
                ->findOrFail($request->query('savedFilterId'));
            \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'branch_daily_reports', $savedFilter->rules);
        }

        // Apply dynamic query rules if provided
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

        $fieldList = \App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig::getMappedFields('BranchDailyReport');

        return $this->paginated(BranchDailyReportResource::collection($reports)->resource, $fieldList);
    }

    public function store(StoreBranchDailyReportRequest $request)
    {
        $values = $request->input('data.values');
        $orgId = $request->user()->organization_id;
        $branchId = $values['branchId'];
        $reportDate = $values['reportDate'];

        // Prevent duplicate reports for the same day
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

            // Pre-process items, check stock, and calculate totals
            foreach ($values['items'] as $item) {
                $productId = $item['productId'];
                $qtySold = (float) $item['quantitySold'];
                $qtyReturned = (float) $item['quantityReturned'];
                $totalDeduction = $qtySold + $qtyReturned;

                if ($totalDeduction <= 0) continue; // Skip empty items

                // 1. Validate Stock
                $branchStock = BranchStock::where('organization_id', $orgId)
                    ->where('branch_id', $branchId)
                    ->where('product_id', $productId)
                    ->lockForUpdate()
                    ->first();

                if (!$branchStock || $branchStock->current_stock < $totalDeduction) {
                    throw new \Exception("Insufficient stock at branch for Product ID: {$productId}");
                }

                // 2. Calculate Revenue/Waste based on Product price
                $product = Product::where('organization_id', $orgId)->findOrFail($productId);
                $unitPrice = (float) $product->price;

                $subtotalRevenue = $qtySold * $unitPrice;
                $subtotalWaste = $qtyReturned * $unitPrice;

                $totalRevenue += $subtotalRevenue;
                $totalWasteAmount += $subtotalWaste;

                // 3. Deduct Stock
                $branchStock->current_stock -= $totalDeduction;
                $branchStock->save();

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
                throw new \Exception("No valid items to report.");
            }

            // Create Report
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

            // Create Items
            foreach ($itemsData as $data) {
                $report->items()->create($data);
            }

            DB::commit();
            $report->load(['branch', 'items.product']);
            
            return $this->success(new BranchDailyReportResource($report), 'Branch Daily Report submitted successfully.', 201);
            
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

            $resource = new BranchDailyReportResource($report);
            $fieldList = \App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig::getMappedFields('BranchDailyReport');
            
            return $this->success([
                'fields' => $fieldList,
                'values' => $resource->toArray(request())
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Daily Report not found.', null, null, null, 404);
        }
    }
}
