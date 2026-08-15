<?php

namespace App\Modules\Api\V1\ProductionBatch\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use App\Modules\Api\V1\InventoryTransaction\Models\InventoryTransaction;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\ProductionBatch\Models\ProductionBatch;
use App\Modules\Api\V1\ProductionBatch\Requests\StoreProductionBatchRequest;
use App\Modules\Api\V1\ProductionBatch\Resources\ProductionBatchResource;
use App\Modules\Api\V1\SavedFilter\Models\SavedFilter;
use App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig;
use App\Modules\Api\V1\SavedFilter\Services\QueryFilterService;
use App\Services\AuthUser;
use App\Services\CRM\RecordObject;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductionBatchController extends Controller
{
    public function index(Request $request)
    {
        $user = AuthUser::requireUser();
        $permissionService = new \App\Services\PermissionService($user);
        if ($deny = $permissionService->denyMessage('ProductionBatch', 'view')) {
            return $this->error($deny, null, null, null, 403);
        }

        $orgId = AuthUser::organizationId();
        $perPage = \App\Support\ApiPagination::perPage($request);

        $query = ProductionBatch::where('organization_id', $orgId);

        $query->when($request->query('search'), function ($q, $search) {
            $q->where('batch_number', 'like', "%{$search}%");
        });

        if ($request->has('savedFilterId')) {
            $savedFilter = SavedFilter::where('organization_id', $orgId)
                ->findOrFail($request->query('savedFilterId'));
            QueryFilterService::apply($query, 'production_batches', $savedFilter->rules);
        }

        if ($request->has('rules')) {
            $rules = $request->input('rules');
            if (is_string($rules)) {
                $rules = json_decode($rules, true);
            }
            if (is_array($rules)) {
                QueryFilterService::apply($query, 'production_batches', $rules);
            }
        }

        if ($request->filled('dateFrom')) {
            $query->whereDate('production_date', '>=', $request->query('dateFrom'));
        }
        if ($request->filled('dateTo')) {
            $query->whereDate('production_date', '<=', $request->query('dateTo'));
        }

        $batches = $query->with('product')->orderBy('created_at', 'desc')->paginate($perPage);
        $fieldList = ModuleFieldConfig::getApiFieldsForView('ProductionBatch', 'DetailView');

        return $this->paginated(ProductionBatchResource::collection($batches)->resource, $fieldList);
    }

    public function store(StoreProductionBatchRequest $request)
    {
        $orgId = AuthUser::organizationId();
        $userId = AuthUser::id();
        $values = $request->input('data.values');

        try {
            DB::beginTransaction();

            try {
                /** @var Product $product */
                $product = RecordObject::make('Product', $values['productId'], [], 'DetailView');
            } catch (\Exception $e) {
                DB::rollBack();
                return $this->error('The selected product does not exist or access is denied.');
            }
            $product->load('recipes');

            if (! $product->isSellable()) {
                DB::rollBack();
                return $this->error('Cannot produce: product is inactive. Activate it first.', null, null, null, 400);
            }

            if ($product->recipes->isEmpty()) {
                DB::rollBack();
                return $this->error('Cannot produce: product has no recipe ingredients. Add a recipe first.', null, null, null, 400);
            }

            $quantityProduced = (float) $values['quantityProduced'];
            $productionDate = Carbon::parse($values['productionDate']);
            $expiryTimestamp = $this->resolveExpiryTimestamp($product, $productionDate);

            /** @var ProductionBatch $batch */
            $batch = RecordObject::make('ProductionBatch', null, [
                'productId' => $product->id,
                'quantityProduced' => $quantityProduced,
                'pieces' => $values['pieces'] ?? null,
                'productionDate' => $values['productionDate'],
                'notes' => $values['notes'] ?? null,
            ], 'CreateView');
            $batch->organization_id = $orgId;
            $batch->product_id = $product->id;
            $batch->quantity_produced = $quantityProduced;
            $batch->pieces = isset($values['pieces']) && $values['pieces'] !== ''
                ? (int) $values['pieces']
                : null;
            $batch->production_date = $productionDate;
            $batch->expiry_timestamp = $expiryTimestamp;
            $batch->status = 'completed';
            $batch->notes = $values['notes'] ?? null;
            $batch->created_by = $userId;
            $batch->save();

            foreach ($product->recipes as $recipe) {
                $totalIngredientNeeded = (float) $recipe->quantity_required * $quantityProduced;

                $ingredient = Ingredient::where('organization_id', $orgId)
                    ->where('id', $recipe->ingredient_id)
                    ->lockForUpdate()
                    ->first();

                if (!$ingredient) {
                    DB::rollBack();
                    return $this->error('Recipe ingredient not found in your organization.', null, null, null, 400);
                }

                if ((float) $ingredient->current_stock < $totalIngredientNeeded) {
                    DB::rollBack();
                    return $this->error(
                        "Insufficient stock for ingredient: {$ingredient->name}. Needed: {$totalIngredientNeeded}, Available: {$ingredient->current_stock}",
                        null,
                        null,
                        null,
                        400
                    );
                }

                $ingredient->current_stock = (float) $ingredient->current_stock - $totalIngredientNeeded;
                $ingredient->save();

                InventoryTransaction::create([
                    'organization_id' => $orgId,
                    'ingredient_id' => $ingredient->id,
                    'type' => 'out',
                    'quantity' => $totalIngredientNeeded,
                    'reference_note' => "Consumed for Production Batch: {$batch->batch_number}",
                    'created_by' => $userId,
                ]);
            }

            $product = Product::where('organization_id', $orgId)->where('id', $product->id)->lockForUpdate()->firstOrFail();
            $product->current_stock = (float) $product->current_stock + $quantityProduced;
            $product->save();

            DB::commit();

            return $this->success(new ProductionBatchResource($batch), 'Production batch logged successfully.', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to log production batch: ' . $e->getMessage(), null, null, null, 500);
        }
    }

    public function show($id)
    {
        try {
            /** @var ProductionBatch $batch */
            $batch = RecordObject::make('ProductionBatch', $id, [], 'DetailView');
            $resource = new ProductionBatchResource($batch);
            $fieldList = ModuleFieldConfig::getApiFieldsForView('ProductionBatch', 'DetailView');

            return $this->success([
                'fields' => $fieldList,
                'values' => $resource->toArray(request()),
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->error('Production Batch not found.', null, null, null, 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
        }
    }

    public function update(Request $request, $id)
    {
        $orgId = AuthUser::organizationId();
        $userId = AuthUser::id();
        $values = $request->input('data.values') ?? [];

        try {
            DB::beginTransaction();

            /** @var ProductionBatch $batch */
            $batch = RecordObject::make('ProductionBatch', $id, [], 'EditView');

            if (strtolower((string) $batch->status) === 'cancelled') {
                DB::rollBack();
                return $this->error('Cancelled production batches cannot be edited.', null, null, null, 400);
            }

            if (isset($values['notes'])) {
                $batch->notes = $values['notes'];
            }

            if (isset($values['productionDate'])) {
                $batch->production_date = Carbon::parse($values['productionDate']);
                $product = $batch->product;
                $batch->expiry_timestamp = $this->resolveExpiryTimestamp($product, $batch->production_date);
            }

            // Explicit cancel via status
            if (isset($values['status']) && strtolower((string) $values['status']) === 'cancelled') {
                $this->reverseProductionStock($batch, $orgId, $userId);
                $batch->status = 'cancelled';
                $batch->save();
                DB::commit();
                return $this->success(new ProductionBatchResource($batch), 'Production batch cancelled and stock reversed.');
            }

            if (isset($values['quantityProduced']) && (float) $values['quantityProduced'] != (float) $batch->quantity_produced) {
                $newQuantity = (float) $values['quantityProduced'];
                if ($newQuantity <= 0) {
                    DB::rollBack();
                    return $this->error('Quantity produced must be greater than zero.', null, null, null, 400);
                }

                $difference = $newQuantity - (float) $batch->quantity_produced;

                /** @var Product $product */
                $product = RecordObject::make('Product', $batch->product_id, [], 'DetailView');
                $product->load('recipes');

                if ($product->recipes->isEmpty()) {
                    DB::rollBack();
                    return $this->error('Cannot adjust production: product has no recipe.', null, null, null, 400);
                }

                foreach ($product->recipes as $recipe) {
                    $totalIngredientDifference = (float) $recipe->quantity_required * $difference;

                    $ingredient = Ingredient::where('organization_id', $orgId)
                        ->where('id', $recipe->ingredient_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$ingredient) {
                        DB::rollBack();
                        return $this->error('Recipe ingredient not found.', null, null, null, 400);
                    }

                    if ($difference > 0 && (float) $ingredient->current_stock < $totalIngredientDifference) {
                        DB::rollBack();
                        return $this->error(
                            "Insufficient stock for ingredient: {$ingredient->name}. Needed: {$totalIngredientDifference}, Available: {$ingredient->current_stock}",
                            null,
                            null,
                            null,
                            400
                        );
                    }

                    $ingredient->current_stock = (float) $ingredient->current_stock - $totalIngredientDifference;
                    if ((float) $ingredient->current_stock < 0) {
                        DB::rollBack();
                        return $this->error("Ingredient stock would go negative for {$ingredient->name}.", null, null, null, 400);
                    }
                    $ingredient->save();

                    InventoryTransaction::create([
                        'organization_id' => $orgId,
                        'ingredient_id' => $ingredient->id,
                        'type' => $difference > 0 ? 'out' : 'in',
                        'quantity' => abs($totalIngredientDifference),
                        'reference_note' => "Adjustment for Production Batch Update: {$batch->batch_number}",
                        'created_by' => $userId,
                    ]);
                }

                $product = Product::where('organization_id', $orgId)->where('id', $product->id)->lockForUpdate()->firstOrFail();
                if ($difference < 0 && (float) $product->current_stock < abs($difference)) {
                    DB::rollBack();
                    return $this->error(
                        "Cannot reduce production: warehouse product stock is insufficient (already transferred/sold).",
                        null,
                        null,
                        null,
                        400
                    );
                }
                $product->current_stock = (float) $product->current_stock + $difference;
                $product->save();

                $batch->quantity_produced = $newQuantity;
            }

            if (array_key_exists('pieces', $values)) {
                if ($values['pieces'] !== null && $values['pieces'] !== '') {
                    if (filter_var($values['pieces'], FILTER_VALIDATE_INT) === false || (int) $values['pieces'] < 0) {
                        DB::rollBack();
                        return $this->error('Pieces must be a whole number of zero or more.', null, null, null, 400);
                    }
                    $batch->pieces = (int) $values['pieces'];
                } else {
                    $batch->pieces = null;
                }
            }

            $batch->save();
            DB::commit();

            return $this->success(new ProductionBatchResource($batch), 'Production batch updated successfully.');
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return $this->error('Production Batch not found.', null, null, null, 404);
        } catch (\RuntimeException $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), null, null, null, 400);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update production batch: ' . $e->getMessage(), null, null, null, 500);
        }
    }

    /**
     * Cancel/reverse: restore ingredients and remove finished goods from warehouse stock.
     * Hard delete of posted batches is not allowed.
     */
    public function destroy($id)
    {
        $orgId = AuthUser::organizationId();
        $userId = AuthUser::id();

        try {
            return DB::transaction(function () use ($id, $orgId, $userId) {
                /** @var ProductionBatch $batch */
                $batch = RecordObject::make('ProductionBatch', $id, [], 'EditView');

                if (strtolower((string) $batch->status) === 'cancelled') {
                    return $this->error('Production batch is already cancelled.', null, null, null, 400);
                }

                $this->reverseProductionStock($batch, $orgId, $userId);
                $batch->status = 'cancelled';
                $batch->save();

                return $this->success(new ProductionBatchResource($batch), 'Production batch cancelled and stock reversed.');
            });
        } catch (ModelNotFoundException $e) {
            return $this->error('Production Batch not found.', null, null, null, 404);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        }
    }

    private function resolveExpiryTimestamp(Product $product, Carbon $productionDate): Carbon
    {
        $hours = (int) ($product->shelf_life ?? 0);
        if ($hours <= 0) {
            // Default half day when product has no shelf life set
            $hours = 12;
        }

        return $productionDate->copy()->addHours($hours);
    }

    private function reverseProductionStock(ProductionBatch $batch, string $orgId, ?string $userId): void
    {
        $product = Product::where('organization_id', $orgId)
            ->where('id', $batch->product_id)
            ->lockForUpdate()
            ->firstOrFail();

        $qty = (float) $batch->quantity_produced;
        if ((float) $product->current_stock < $qty) {
            throw new \RuntimeException(
                'Cannot reverse production: warehouse finished-goods stock is insufficient (already transferred or sold).'
            );
        }

        $product->load('recipes');
        foreach ($product->recipes as $recipe) {
            $restoreQty = (float) $recipe->quantity_required * $qty;
            $ingredient = Ingredient::where('organization_id', $orgId)
                ->where('id', $recipe->ingredient_id)
                ->lockForUpdate()
                ->first();
            if (!$ingredient) {
                continue;
            }
            $ingredient->current_stock = (float) $ingredient->current_stock + $restoreQty;
            $ingredient->save();

            InventoryTransaction::create([
                'organization_id' => $orgId,
                'ingredient_id' => $ingredient->id,
                'type' => 'in',
                'quantity' => $restoreQty,
                'reference_note' => "Reversed Production Batch: {$batch->batch_number}",
                'created_by' => $userId,
            ]);
        }

        $product->current_stock = (float) $product->current_stock - $qty;
        $product->save();
    }
}
