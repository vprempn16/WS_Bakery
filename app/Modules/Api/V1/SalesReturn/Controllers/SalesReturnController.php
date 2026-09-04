<?php

namespace App\Modules\Api\V1\SalesReturn\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\Billing\Services\BillingPriceService;
use App\Modules\Api\V1\Billing\Services\BillingStockService;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\SalesReturn\Models\SalesReturn;
use App\Modules\Api\V1\SalesReturn\Models\SalesReturnItem;
use App\Modules\Api\V1\SalesReturn\Requests\StoreSalesReturnRequest;
use App\Modules\Api\V1\SalesReturn\Resources\SalesReturnResource;
use App\Modules\Api\V1\SavedFilter\Models\SavedFilter;
use App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig;
use App\Modules\Api\V1\SavedFilter\Services\QueryFilterService;
use App\Services\BranchAccess;
use App\Services\PermissionService;
use App\Support\ApiPagination;
use App\Support\Idempotency;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesReturnController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $permissionService = new PermissionService($user);
        if ($deny = $permissionService->denyMessage('SalesReturn', 'view')) {
            return $this->error($deny, null, null, null, 403);
        }

        $orgId = $user->organization_id;
        $perPage = ApiPagination::perPage($request);

        $query = SalesReturn::with(['branch'])
            ->withCount('items')
            ->where('organization_id', $orgId);

        try {
            BranchAccess::applyListBranchScope($query, $request, $user);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
        }

        $query->when($request->query('search'), function ($q, $search) {
            $like = "%{$search}%";
            $q->where(function ($sub) use ($like) {
                $sub->where('return_number', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhereHas('items.product', function ($productQuery) use ($like) {
                        $productQuery->where('name', 'like', $like);
                    });
            });
        });

        if ($request->has('savedFilterId')) {
            $savedFilter = SavedFilter::where('organization_id', $orgId)
                ->findOrFail($request->query('savedFilterId'));
            QueryFilterService::apply($query, 'sales_returns', $savedFilter->rules);
        }

        if ($request->has('rules')) {
            $rules = $request->input('rules');
            if (is_string($rules)) {
                $rules = json_decode($rules, true);
            }
            if (is_array($rules)) {
                QueryFilterService::apply($query, 'sales_returns', $rules);
            }
        }

        if ($request->filled('dateFrom')) {
            $query->whereDate('return_date', '>=', $request->query('dateFrom'));
        }
        if ($request->filled('dateTo')) {
            $query->whereDate('return_date', '<=', $request->query('dateTo'));
        }

        $returns = $query->orderBy('return_date', 'desc')->orderBy('created_at', 'desc')->paginate($perPage);
        $fieldList = ModuleFieldConfig::getApiFieldsForView('SalesReturn', 'DetailView');

        return $this->paginated(SalesReturnResource::collection($returns)->resource, $fieldList);
    }

    public function store(StoreSalesReturnRequest $request)
    {
        $user = $request->user();
        $permissionService = new PermissionService($user);
        if ($deny = $permissionService->denyMessage('SalesReturn', 'create')) {
            return $this->error($deny, null, null, null, 403);
        }

        [$lock, $cacheKey, $early] = Idempotency::begin(
            'sales-return:create',
            $request->header('Idempotency-Key'),
            true
        );
        if ($early) {
            return $early;
        }

        $values = $request->input('data.values');
        $itemsData = $request->input('data.relatedRecords.items', []);

        $orgId = $user->organization_id;
        $branchId = BranchAccess::resolveBranchIdFromRequest($request, $user)
            ?: ($values['branchId'] ?? null);

        if (! $branchId) {
            Idempotency::release($lock);

            return $this->error('Select an active branch before logging a return.', null, null, null, 422);
        }

        try {
            BranchAccess::assertCanAccessBranch($user, (string) $branchId);
        } catch (\RuntimeException $e) {
            Idempotency::release($lock);

            return $this->error($e->getMessage(), null, null, null, 403);
        }

        try {
            $response = DB::transaction(function () use (
                $orgId,
                $branchId,
                $itemsData,
                $values,
                $user
            ) {
                $record = SalesReturn::create([
                    'organization_id' => $orgId,
                    'branch_id' => $branchId,
                    'return_date' => $values['returnDate'],
                    'notes' => $values['notes'] ?? null,
                    'created_by' => $user->id,
                    'total_return_value' => 0,
                ]);

                $stockItems = [];
                $totalReturnValue = 0.0;

                foreach ($itemsData as $itemData) {
                    $productId = (string) ($itemData['productId'] ?? '');
                    $product = Product::where('organization_id', $orgId)->find($productId);
                    if (! $product) {
                        throw new \RuntimeException('A selected product does not exist or access is denied.');
                    }
                    if (! $product->isSellable()) {
                        throw new \RuntimeException("Cannot return inactive product: {$product->name}.");
                    }

                    $quantity = $this->resolveReturnItemQuantity($product, $itemData);
                    if ($quantity < 0.01) {
                        throw new \RuntimeException("Enter a valid quantity for {$product->name}.");
                    }

                    // Always use catalog price (never trust client unitPrice for loss value).
                    $unitPrice = (float) ($product->price ?? 0);
                    $lineUnit = $product->unit;
                    $lineValue = BillingPriceService::lineTotal($quantity, $unitPrice, $lineUnit);
                    $totalReturnValue += $lineValue;

                    $piecesRaw = $itemData['pieces'] ?? null;
                    $pieces = null;
                    if ($piecesRaw !== null && $piecesRaw !== '') {
                        $pieces = (float) $piecesRaw;
                    }

                    $item = new SalesReturnItem();
                    $item->organization_id = $orgId;
                    $item->sales_return_id = $record->id;
                    $item->product_id = $productId;
                    $item->quantity = $quantity;
                    $item->unit = $product->unit;
                    $item->pieces = $pieces;
                    $item->unit_price = $unitPrice;
                    $item->return_value = $lineValue;
                    $item->save();

                    $stockItems[] = [
                        'productId' => $productId,
                        'quantity' => $quantity,
                    ];
                }

                $record->total_return_value = round($totalReturnValue, 2);
                $record->save();

                // Wastage: deduct branch stock (never add back)
                app(BillingStockService::class)->deductForSale($orgId, (string) $branchId, $stockItems);

                $record = $record->load(['branch', 'items.product'])->loadCount('items');

                return $this->success(
                    new SalesReturnResource($record),
                    'Wastage logged — branch stock reduced.',
                    201
                );
            });

            Idempotency::remember($cacheKey, $response);

            return $response;
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        } catch (\Throwable $e) {
            return $this->error('Failed to log return: ' . $e->getMessage(), null, null, null, 500);
        } finally {
            Idempotency::release($lock);
        }
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $permissionService = new PermissionService($user);
        if ($deny = $permissionService->denyMessage('SalesReturn', 'view')) {
            return $this->error($deny, null, null, null, 403);
        }

        try {
            $return = SalesReturn::with(['branch', 'items.product'])
                ->withCount('items')
                ->where('organization_id', $user->organization_id)
                ->findOrFail($id);

            BranchAccess::assertCanAccessBranch($user, (string) $return->branch_id);

            $fieldList = ModuleFieldConfig::getApiFieldsForView('SalesReturn', 'DetailView');

            $resource = new SalesReturnResource($return);

            return $this->success([
                'fields' => $fieldList,
                'values' => $resource->toArray(request()),
            ]);
        } catch (ModelNotFoundException) {
            return $this->error('Return record not found.', null, null, null, 404);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
        }
    }

    /**
     * @param  array{quantity?:float|int|string|null, pieces?:float|int|string|null}  $itemData
     */
    private function resolveReturnItemQuantity(Product $product, array $itemData): float
    {
        $unit = strtolower(trim((string) ($product->unit ?? '')));
        $isPieceUnit = in_array($unit, ['pcs', 'pc', 'piece', 'pieces'], true);
        $quantity = isset($itemData['quantity']) && $itemData['quantity'] !== '' && is_numeric($itemData['quantity'])
            ? (float) $itemData['quantity']
            : 0.0;

        if ($isPieceUnit && $quantity < 0.01) {
            $pieces = $itemData['pieces'] ?? null;
            if ($pieces !== null && $pieces !== '' && is_numeric($pieces)) {
                return (float) $pieces;
            }
        }

        return $quantity;
    }
}
