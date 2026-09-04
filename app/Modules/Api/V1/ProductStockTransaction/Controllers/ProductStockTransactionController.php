<?php

namespace App\Modules\Api\V1\ProductStockTransaction\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\ProductStockTransaction\Models\ProductStockTransaction;
use App\Modules\Api\V1\ProductStockTransaction\Requests\StoreProductStockTransactionRequest;
use App\Modules\Api\V1\ProductStockTransaction\Resources\ProductStockTransactionResource;
use App\Services\AuthUser;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductStockTransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = AuthUser::requireUser();
        $permissionService = new PermissionService($user);
        if (! $permissionService->hasPermission('ProductStockTransaction', 'view')
            && ! $permissionService->hasPermission('Product', 'view')) {
            return $this->error("You don't have permission to view ProductStockTransaction.", null, null, null, 403);
        }

        $orgId = $user->organization_id;
        $perPage = \App\Support\ApiPagination::perPage($request);

        $query = ProductStockTransaction::with(['product', 'organization'])
            ->where('organization_id', $orgId);

        $query->when($request->query('productId'), function ($q, $productId) {
            $q->where('product_id', $productId);
        });

        $query->when($request->query('type'), function ($q, $type) {
            $q->where('type', $type);
        });

        $transactions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $fieldList = \App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig::getApiFieldsForView('ProductStockTransaction', 'DetailView');

        return $this->paginated(ProductStockTransactionResource::collection($transactions)->resource, $fieldList);
    }

    public function store(StoreProductStockTransactionRequest $request)
    {
        $values = $request->input('data.values');
        $orgId = AuthUser::organizationId();
        $userId = AuthUser::id();

        try {
            $user = AuthUser::requireUser();
            $permissionService = new PermissionService($user);
            if (! $permissionService->hasPermission('ProductStockTransaction', 'create')
                && ! $permissionService->hasPermission('Product', 'edit')) {
                return $this->error("You don't have permission to create ProductStockTransaction.", null, null, null, 403);
            }

            return DB::transaction(function () use ($values, $orgId, $userId) {
                $product = Product::where('organization_id', $orgId)
                    ->where('id', $values['productId'])
                    ->lockForUpdate()
                    ->first();

                if (! $product) {
                    throw new \RuntimeException('Product not found in your organization.');
                }

                if (! $product->isBought()) {
                    throw new \RuntimeException(
                        'Receive Stock is only for bought (outside brand) products. Own products use Production Batch.'
                    );
                }

                if (! $product->isSellable()) {
                    throw new \RuntimeException('Cannot receive stock: product is inactive. Activate it first.');
                }

                $qty = (float) $values['quantity'];
                $type = $values['type'];

                if ($type !== 'in') {
                    throw new \RuntimeException('Only stock-in is supported for bought products.');
                }

                $product->current_stock = (float) $product->current_stock + $qty;
                $product->save();

                $transaction = ProductStockTransaction::create([
                    'organization_id' => $orgId,
                    'product_id' => $product->id,
                    'type' => $type,
                    'quantity' => $qty,
                    'reference_note' => $values['referenceNote'] ?? null,
                    'created_by' => $userId,
                ]);

                $transaction->load('product');

                return $this->success(
                    new ProductStockTransactionResource($transaction),
                    'Stock received successfully.',
                    201
                );
            });
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        } catch (\Exception $e) {
            return $this->error('Failed to receive stock: '.$e->getMessage(), null, null, null, 500);
        }
    }

    public function show($id)
    {
        try {
            $orgId = AuthUser::organizationId();
            $transaction = ProductStockTransaction::where('organization_id', $orgId)
                ->with('product')
                ->findOrFail($id);
            $resource = new ProductStockTransactionResource($transaction);

            $fieldList = \App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig::getApiFieldsForView('ProductStockTransaction', 'DetailView');

            return $this->success([
                'fields' => $fieldList,
                'values' => $resource->toArray(request()),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Product stock transaction not found.', null, null, null, 404);
        }
    }
}
