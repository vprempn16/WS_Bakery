<?php

namespace App\Modules\Api\V1\ProductionBatch\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\ProductionBatch\Models\ProductionBatch;
use App\Modules\Api\V1\ProductionBatch\Requests\StoreProductionBatchRequest;
use App\Modules\Api\V1\ProductionBatch\Resources\ProductionBatchResource;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use App\Modules\Api\V1\InventoryTransaction\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductionBatchController extends Controller
{
    public function index(Request $request)
    {
        $orgId = $request->user()->organization_id;
        $perPage = $request->query('limit', $request->query('per_page', 20));

        $query = ProductionBatch::where('organization_id', $orgId);

        $query->when($request->query('search'), function ($q, $search) {
            $q->where('batch_number', 'like', "%{$search}%");
        });

        // Apply saved filter if provided
        if ($request->has('savedFilterId')) {
            $savedFilter = \App\Modules\Api\V1\SavedFilter\Models\SavedFilter::where('organization_id', $orgId)
                ->findOrFail($request->query('savedFilterId'));
            \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'production_batches', $savedFilter->rules);
        }

        // Apply dynamic query rules if provided
        if ($request->has('rules')) {
            $rules = $request->input('rules');
            if (is_string($rules)) {
                $rules = json_decode($rules, true);
            }
            if (is_array($rules)) {
                \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'production_batches', $rules);
            }
        }

        $batches = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $fieldList = \App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig::getMappedFields('ProductionBatch');

        return $this->paginated(ProductionBatchResource::collection($batches)->resource, $fieldList);
    }

    public function store(StoreProductionBatchRequest $request)
    {
        $orgId = $request->user()->organization_id;
        $userId = $request->user()->id;
        $values = $request->input('data.values');

        try {
            DB::beginTransaction();

            $product = Product::where('organization_id', $orgId)
                ->with('recipes')
                ->findOrFail($values['productId']);

            $quantityProduced = (float) $values['quantityProduced'];
            $productionDate = Carbon::parse($values['productionDate']);
            
            // Calculate Expiry Timestamp
            $expiryTimestamp = null;
            if ($product->shelf_life_hours > 0) {
                $expiryTimestamp = $productionDate->copy()->addHours($product->shelf_life_hours);
            } elseif ($product->shelf_life_days > 0) {
                $expiryTimestamp = $productionDate->copy()->addDays($product->shelf_life_days);
            } else {
                // Default: 12 hours validity if not specified
                $expiryTimestamp = $productionDate->copy()->addHours(12);
            }

            // Create the Production Batch record first to get the batch number for reference notes
            $batch = new ProductionBatch([
                'organization_id' => $orgId,
                'product_id' => $product->id,
                'quantity_produced' => $quantityProduced,
                'production_date' => $productionDate,
                'expiry_timestamp' => $expiryTimestamp,
                'status' => 'completed',
                'notes' => $values['notes'] ?? null,
                'created_by' => $userId,
            ]);
            $batch->save(); // This will trigger the boot method and auto-generate batch_number

            // Process Ingredients Deduction
            foreach ($product->recipes as $recipe) {
                $totalIngredientNeeded = $recipe->quantity_required * $quantityProduced;

                $ingredient = Ingredient::where('organization_id', $orgId)
                    ->where('id', $recipe->ingredient_id)
                    ->lockForUpdate()
                    ->first();

                if ($ingredient) {
                    $ingredient->current_stock -= $totalIngredientNeeded;
                    $ingredient->save();

                    // Log Inventory Transaction
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

            // Add Finished Goods Stock
            $product->current_stock += $quantityProduced;
            $product->save();

            DB::commit();

            return $this->success(new ProductionBatchResource($batch), 'Production batch logged successfully.', 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            // Typically you'd log the exception here: \Log::error($e);
            return $this->error('Failed to log production batch: ' . $e->getMessage(), null, null, null, 500);
        }
    }

    public function show($id)
    {
        try {
            $orgId = auth()->user()->organization_id;
            $batch = ProductionBatch::where('organization_id', $orgId)->findOrFail($id);
            $resource = new ProductionBatchResource($batch);
            
            $fieldList = \App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig::getMappedFields('ProductionBatch');
            
            return $this->success([
                'fields' => $fieldList,
                'values' => $resource->toArray(request())
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Production Batch not found.', null, null, null, 404);
        }
    }

    public function update(Request $request, $id)
    {
        $orgId = $request->user()->organization_id;
        $userId = $request->user()->id;
        $values = $request->input('data.values');

        try {
            DB::beginTransaction();

            $batch = ProductionBatch::where('organization_id', $orgId)->findOrFail($id);

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
                
                $product = Product::where('organization_id', $orgId)
                    ->with('recipes')
                    ->findOrFail($batch->product_id);

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
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
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
            $orgId = auth()->user()->organization_id;
            $batch = ProductionBatch::where('organization_id', $orgId)->findOrFail($id);
            
            // Phase 2: Simple delete without transaction reversal.
            $batch->delete();

            return $this->success(null, 'Production Batch successfully deleted.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Production Batch not found.', null, null, null, 404);
        }
    }
}
