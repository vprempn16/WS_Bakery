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
        $orgId = AuthUser::organizationId();
        $perPage = $request->query('limit', $request->query('per_page', 20));

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

        $batches = $query->orderBy('created_at', 'desc')->paginate($perPage);
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

            $quantityProduced = (float) $values['quantityProduced'];
            $productionDate = Carbon::parse($values['productionDate']);

            if ($product->shelf_life_hours > 0) {
                $expiryTimestamp = $productionDate->copy()->addHours($product->shelf_life_hours);
            } elseif ($product->shelf_life_days > 0) {
                $expiryTimestamp = $productionDate->copy()->addDays($product->shelf_life_days);
            } else {
                $expiryTimestamp = $productionDate->copy()->addHours(12);
            }

            /** @var ProductionBatch $batch */
            $batch = RecordObject::make('ProductionBatch', null, [
                'productId' => $product->id,
                'quantityProduced' => $quantityProduced,
                'productionDate' => $values['productionDate'],
                'notes' => $values['notes'] ?? null,
            ], 'CreateView');
            $batch->organization_id = $orgId;
            $batch->product_id = $product->id;
            $batch->quantity_produced = $quantityProduced;
            $batch->production_date = $productionDate;
            $batch->expiry_timestamp = $expiryTimestamp;
            $batch->status = 'completed';
            $batch->notes = $values['notes'] ?? null;
            $batch->created_by = $userId;
            $batch->save();

            foreach ($product->recipes as $recipe) {
                $totalIngredientNeeded = $recipe->quantity_required * $quantityProduced;

                $ingredient = Ingredient::where('organization_id', $orgId)
                    ->where('id', $recipe->ingredient_id)
                    ->lockForUpdate()
                    ->first();

                if ($ingredient) {
                    if ($ingredient->current_stock < $totalIngredientNeeded) {
                        DB::rollBack();
                        return $this->error("Insufficient stock for ingredient: {$ingredient->name}. Needed: {$totalIngredientNeeded}, Available: {$ingredient->current_stock}", null, null, null, 400);
                    }

                    $ingredient->current_stock -= $totalIngredientNeeded;
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
            }

            $product->current_stock += $quantityProduced;
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

            // Allow updating status, notes, and production date directly
            if (isset($values['status'])) {
                $batch->status = $values['status'];
            }
            if (isset($values['notes'])) {
                $batch->notes = $values['notes'];
            }
            
            if (isset($values['productionDate'])) {
                $batch->production_date = Carbon::parse($values['productionDate']);
                // Recalculate expiry
                $product = $batch->product;
                if ($product->shelf_life_hours > 0) {
                    $batch->expiry_timestamp = $batch->production_date->copy()->addHours($product->shelf_life_hours);
                } elseif ($product->shelf_life_days > 0) {
                    $batch->expiry_timestamp = $batch->production_date->copy()->addDays($product->shelf_life_days);
                } else {
                    $batch->expiry_timestamp = $batch->production_date->copy()->addHours(12);
                }
            }

            // Optional: Handle quantity updates (complex)
            if (isset($values['quantityProduced']) && $values['quantityProduced'] != $batch->quantity_produced) {
                $newQuantity = (float) $values['quantityProduced'];
                $difference = $newQuantity - $batch->quantity_produced;
                
                /** @var Product $product */
                $product = RecordObject::make('Product', $batch->product_id, [], 'DetailView');
                $product->load('recipes');

                foreach ($product->recipes as $recipe) {
                    $totalIngredientDifference = $recipe->quantity_required * $difference;

                    $ingredient = Ingredient::where('organization_id', $orgId)
                        ->where('id', $recipe->ingredient_id)
                        ->lockForUpdate()
                        ->first();

                    if ($ingredient) {
                        $ingredient->current_stock -= $totalIngredientDifference;
                        $ingredient->save();

                        // Log Inventory Transaction for the difference
                        $type = $difference > 0 ? 'out' : 'in';
                        InventoryTransaction::create([
                            'organization_id' => $orgId,
                            'ingredient_id' => $ingredient->id,
                            'type' => $type,
                            'quantity' => abs($totalIngredientDifference),
                            'reference_note' => "Adjustment for Production Batch Update: {$batch->batch_number}",
                            'created_by' => $userId,
                        ]);
                    }
                }

                $product->current_stock += $difference;
                $product->save();

                $batch->quantity_produced = $newQuantity;
            }

            $batch->save();

            DB::commit();

            return $this->success(new ProductionBatchResource($batch), 'Production batch updated successfully.');
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return $this->error('Production Batch not found.', null, null, null, 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update production batch: ' . $e->getMessage(), null, null, null, 500);
        }
    }

    public function destroy($id)
    {
        try {
            /** @var ProductionBatch $batch */
            $batch = RecordObject::make('ProductionBatch', $id, [], 'EditView');
            $batch->deleteRecord();

            return $this->success(null, 'Production Batch successfully deleted.');
        } catch (ModelNotFoundException $e) {
            return $this->error('Production Batch not found.', null, null, null, 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        }
    }
}
