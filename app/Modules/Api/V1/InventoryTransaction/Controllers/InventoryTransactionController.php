<?php

namespace App\Modules\Api\V1\InventoryTransaction\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use App\Modules\Api\V1\InventoryTransaction\Models\InventoryTransaction;
use App\Modules\Api\V1\InventoryTransaction\Requests\StoreInventoryTransactionRequest;
use App\Modules\Api\V1\InventoryTransaction\Resources\InventoryTransactionResource;
use App\Services\AuthUser;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryTransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = AuthUser::requireUser();
        $permissionService = new PermissionService($user);
        if (!$permissionService->hasPermission('InventoryTransaction', 'view')) {
            return $this->error("You don't have permission to view InventoryTransaction.", null, null, null, 403);
        }

        $orgId = $user->organization_id;
        $perPage = \App\Support\ApiPagination::perPage($request);

        $query = InventoryTransaction::where('organization_id', $orgId);

        $query->when($request->query('ingredientId'), function ($q, $ingredientId) {
            $q->where('ingredient_id', $ingredientId);
        });

        $query->when($request->query('type'), function ($q, $type) {
            $q->where('type', $type);
        });

        $query->when($request->query('startDate'), function ($q, $startDate) {
            $q->whereDate('created_at', '>=', $startDate);
        });

        $query->when($request->query('endDate'), function ($q, $endDate) {
            $q->whereDate('created_at', '<=', $endDate);
        });

        if ($request->has('savedFilterId')) {
            $savedFilter = \App\Modules\Api\V1\SavedFilter\Models\SavedFilter::where('organization_id', $orgId)
                ->findOrFail($request->query('savedFilterId'));
            \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'inventory_transactions', $savedFilter->rules);
        }

        if ($request->has('rules')) {
            $rules = $request->input('rules');
            if (is_string($rules)) {
                $rules = json_decode($rules, true);
            }
            if (is_array($rules)) {
                \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'inventory_transactions', $rules);
            }
        }

        $transactions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $fieldList = \App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig::getApiFieldsForView('InventoryTransaction', 'DetailView');

        return $this->paginated(InventoryTransactionResource::collection($transactions)->resource, $fieldList);
    }

    public function store(StoreInventoryTransactionRequest $request)
    {
        $values = $request->input('data.values');
        $orgId = AuthUser::organizationId();
        $userId = AuthUser::id();

        try {
            // Permission check without requiring BKModel return type
            $user = AuthUser::requireUser();
            $permissionService = new PermissionService($user);
            if (!$permissionService->hasPermission('InventoryTransaction', 'create')) {
                return $this->error("You don't have permission to create InventoryTransaction.", null, null, null, 403);
            }

            return DB::transaction(function () use ($values, $orgId, $userId) {
                $ingredient = Ingredient::where('organization_id', $orgId)
                    ->where('id', $values['ingredientId'])
                    ->lockForUpdate()
                    ->first();

                if (!$ingredient) {
                    throw new \RuntimeException('Ingredient not found in your organization.');
                }

                $qty = (float) $values['quantity'];
                $type = $values['type'];

                if (in_array($type, ['out', 'waste', 'production'], true)) {
                    if ((float) $ingredient->current_stock < $qty) {
                        throw new \RuntimeException(
                            "Insufficient stock for {$ingredient->name}. Needed: {$qty}, Available: {$ingredient->current_stock}"
                        );
                    }
                    $ingredient->current_stock = (float) $ingredient->current_stock - $qty;
                } else {
                    // in
                    $ingredient->current_stock = (float) $ingredient->current_stock + $qty;
                }

                $ingredient->save();

                $transaction = InventoryTransaction::create([
                    'organization_id' => $orgId,
                    'ingredient_id' => $ingredient->id,
                    'type' => $type,
                    'quantity' => $qty,
                    'reference_note' => $values['referenceNote'] ?? null,
                    'created_by' => $userId,
                ]);

                return $this->success(new InventoryTransactionResource($transaction), 'Transaction created successfully.', 201);
            });
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        } catch (\Exception $e) {
            return $this->error('Failed to create transaction: ' . $e->getMessage(), null, null, null, 500);
        }
    }

    public function show($id)
    {
        try {
            $orgId = AuthUser::organizationId();
            $transaction = InventoryTransaction::where('organization_id', $orgId)->findOrFail($id);
            $resource = new InventoryTransactionResource($transaction);

            $fieldList = \App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig::getApiFieldsForView('InventoryTransaction', 'DetailView');

            return $this->success([
                'fields' => $fieldList,
                'values' => $resource->toArray(request()),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Inventory Transaction not found.', null, null, null, 404);
        }
    }
}
