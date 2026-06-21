<?php

namespace App\Modules\Api\V1\BranchTransfer\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use App\Modules\Api\V1\BranchTransfer\Resources\BranchStockResource;
use Illuminate\Http\Request;

class BranchStockController extends Controller
{
    public function index(Request $request)
    {
        $orgId = $request->user()->organization_id;
        $perPage = $request->query('per_page', 20);

        $query = BranchStock::with(['branch', 'product'])
            ->where('organization_id', $orgId);

        // Filters
        $query->when($request->query('branchId'), function ($q, $branchId) {
            $q->where('branch_id', $branchId);
        });

        $query->when($request->query('productId'), function ($q, $productId) {
            $q->where('product_id', $productId);
        });

        // Apply saved filter if provided
        if ($request->has('savedFilterId')) {
            $savedFilter = \App\Modules\Api\V1\SavedFilter\Models\SavedFilter::where('organization_id', $orgId)
                ->findOrFail($request->query('savedFilterId'));
            \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'branch_stocks', $savedFilter->rules);
        }

        // Apply dynamic query rules if provided
        if ($request->has('rules')) {
            $rules = $request->input('rules');
            if (is_string($rules)) {
                $rules = json_decode($rules, true);
            }
            if (is_array($rules)) {
                \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'branch_stocks', $rules);
            }
        }

        $stocks = $query->paginate($perPage);

        $fieldList = \App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig::getMappedFields('BranchStock');

        return $this->paginated(BranchStockResource::collection($stocks)->resource, $fieldList);
    }
}
