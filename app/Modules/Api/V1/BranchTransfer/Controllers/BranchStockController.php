<?php

namespace App\Modules\Api\V1\BranchTransfer\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use App\Modules\Api\V1\BranchTransfer\Resources\BranchStockResource;
use App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig;
use App\Services\BranchAccess;
use App\Services\PermissionService;
use App\Services\ShelfLifeStatusService;
use App\Support\ApiPagination;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

        // BranchStock is a current ledger — do not filter by updated_at date range
        // (that hid stock after transfers when "today" did not match credit time).

        $stocks = $query->orderBy('updated_at', 'desc')->paginate($perPage);

        $productIds = $stocks->getCollection()->pluck('product_id')->filter()->unique()->values()->all();
        $stockByProduct = $stocks->getCollection()
            ->mapWithKeys(fn ($row) => [(string) $row->product_id => (float) $row->current_stock])
            ->all();
        $shelfMap = ShelfLifeStatusService::statusForProducts((string) $orgId, $productIds, $stockByProduct);

        $stocks->getCollection()->transform(function ($row) use ($shelfMap) {
            $info = $shelfMap[(string) $row->product_id] ?? null;
            $row->setAttribute('shelf_status_computed', $info['shelfStatus'] ?? null);
            $row->setAttribute('earliest_expiry_computed', $info['earliestExpiry'] ?? null);

            return $row;
        });

        $fieldList = ModuleFieldConfig::getApiFieldsForView('BranchStock', 'DetailView');

        return $this->paginated(BranchStockResource::collection($stocks)->resource, $fieldList);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $permissionService = new PermissionService($user);
        if (! $permissionService->hasPermission('BranchStock', 'view')) {
            return $this->error("You don't have permission to view BranchStock.", null, null, null, 403);
        }

        try {
            $stock = BranchStock::with(['branch', 'product'])
                ->where('organization_id', $user->organization_id)
                ->findOrFail($id);

            BranchAccess::assertCanAccessBranch($user, (string) $stock->branch_id);

            $shelfMap = ShelfLifeStatusService::statusForProducts(
                (string) $user->organization_id,
                [(string) $stock->product_id],
                [(string) $stock->product_id => (float) $stock->current_stock]
            );
            $info = $shelfMap[(string) $stock->product_id] ?? null;
            $stock->setAttribute('shelf_status_computed', $info['shelfStatus'] ?? null);
            $stock->setAttribute('earliest_expiry_computed', $info['earliestExpiry'] ?? null);

            $fieldList = ModuleFieldConfig::getApiFieldsForView('BranchStock', 'DetailView');
            $resource = new BranchStockResource($stock);

            return $this->success([
                'fields' => $fieldList,
                'values' => $resource->toArray(request()),
            ]);
        } catch (ModelNotFoundException) {
            return $this->error('Branch stock record not found.', null, null, null, 404);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
        }
    }
}
