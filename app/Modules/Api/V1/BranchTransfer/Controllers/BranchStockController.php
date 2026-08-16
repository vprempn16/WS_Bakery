<?php

namespace App\Modules\Api\V1\BranchTransfer\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use App\Modules\Api\V1\BranchTransfer\Resources\BranchStockResource;
use App\Services\BranchAccess;
use App\Services\PermissionService;
use App\Support\ApiPagination;
use Illuminate\Http\Request;

class BranchStockController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $permissionService = new PermissionService($user);
        if (! $permissionService->hasPermission('BranchStock', 'view')) {
            return $this->error("You don't have permission to view BranchStock.", null, null, null, 403);
        }

        $orgId = $user->organization_id;
        $perPage = ApiPagination::perPage($request);

        $query = BranchStock::with(['branch', 'product'])
            ->where('organization_id', $orgId);

        try {
            BranchAccess::applyListBranchScope($query, $request, $user);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
        }

        $query->when($request->query('productId'), function ($q, $productId) {
            $q->where('product_id', $productId);
        });

        if ($request->has('savedFilterId')) {
            $savedFilter = \App\Modules\Api\V1\SavedFilter\Models\SavedFilter::where('organization_id', $orgId)
                ->findOrFail($request->query('savedFilterId'));
            \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'branch_stocks', $savedFilter->rules);
        }

        if ($request->has('rules')) {
            $rules = $request->input('rules');
            if (is_string($rules)) {
                $rules = json_decode($rules, true);
            }
            if (is_array($rules)) {
                \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'branch_stocks', $rules);
            }
        }

        if ($request->filled('dateFrom')) {
            $query->whereDate('updated_at', '>=', $request->query('dateFrom'));
        }
        if ($request->filled('dateTo')) {
            $query->whereDate('updated_at', '<=', $request->query('dateTo'));
        }

        $stocks = $query->paginate($perPage);

        $fieldList = \App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig::getApiFieldsForView('BranchStock', 'DetailView');

        return $this->paginated(BranchStockResource::collection($stocks)->resource, $fieldList);
    }
}
