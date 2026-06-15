<?php

namespace App\Modules\Api\V1\Ingredient\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use App\Modules\Api\V1\Ingredient\Requests\StoreIngredientRequest;
use App\Modules\Api\V1\Ingredient\Requests\UpdateIngredientRequest;
use App\Modules\Api\V1\Ingredient\Resources\IngredientResource;
use Illuminate\Http\Request;

class IngredientController extends Controller
{
    public function index(Request $request)
    {
        $orgId = $request->user()->organization_id;
        $perPage = $request->query('per_page', 20);

        $query = Ingredient::where('organization_id', $orgId);

        $query->when($request->query('search'), function ($q, $search) {
            $q->where('name', 'like', "%{$search}%");
        });

        $query->when($request->query('vendorId'), function ($q, $vendorId) {
            $q->where('vendor_id', $vendorId);
        });

        $query->when($request->query('stockStatus'), function ($q, $stockStatus) {
            if ($stockStatus === 'low') {
                $q->whereColumn('current_stock', '<', 'minimum_stock_level');
            } elseif ($stockStatus === 'in_stock') {
                $q->whereColumn('current_stock', '>=', 'minimum_stock_level');
            }
        });

        // Apply saved filter if provided
        if ($request->has('savedFilterId')) {
            $savedFilter = \App\Modules\Api\V1\SavedFilter\Models\SavedFilter::where('organization_id', $orgId)
                ->findOrFail($request->query('savedFilterId'));
            \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'ingredients', $savedFilter->rules);
        }

        // Apply dynamic query rules if provided
        if ($request->has('rules')) {
            $rules = $request->input('rules');
            if (is_string($rules)) {
                $rules = json_decode($rules, true);
            }
            if (is_array($rules)) {
                \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'ingredients', $rules);
            }
        }

        $ingredients = $query->paginate($perPage);

        return $this->paginated(IngredientResource::collection($ingredients)->resource);
    }

    public function store(StoreIngredientRequest $request)
    {
        $values = $request->input('data.values');
        $orgId = $request->user()->organization_id;

        $ingredient = Ingredient::create([
            'organization_id' => $orgId,
            'vendor_id' => $values['vendorId'] ?? null,
            'name' => $values['name'],
            'unit' => $values['unit'] ?? 'g',
            'minimum_stock_level' => $values['minimumStockLevel'] ?? 0,
            'current_stock' => $values['currentStock'] ?? 0,
        ]);

        return $this->success(new IngredientResource($ingredient), 'Ingredient created successfully.', 201);
    }

    public function show(Request $request, $id)
    {
        $orgId = $request->user()->organization_id;
        $ingredient = Ingredient::where('organization_id', $orgId)->findOrFail($id);
        return $this->success(new IngredientResource($ingredient));
    }

    public function update(UpdateIngredientRequest $request, $id)
    {
        $orgId = $request->user()->organization_id;
        $ingredient = Ingredient::where('organization_id', $orgId)->findOrFail($id);
        $values = $request->input('data.values');

        $ingredient->update([
            'vendor_id' => $values['vendorId'] ?? null,
            'name' => $values['name'],
            'unit' => $values['unit'] ?? 'g',
            'minimum_stock_level' => $values['minimumStockLevel'] ?? 0,
        ]);

        return $this->success(new IngredientResource($ingredient));
    }

    public function destroy(Request $request, $id)
    {
        $orgId = $request->user()->organization_id;
        $ingredient = Ingredient::where('organization_id', $orgId)->findOrFail($id);
        $ingredient->delete();

        return $this->success(null, 'Ingredient successfully deleted.');
    }

    public function lowStock(Request $request)
    {
        $orgId = $request->user()->organization_id;
        $perPage = $request->query('per_page', 20);
        $ingredients = Ingredient::where('organization_id', $orgId)
            ->whereColumn('current_stock', '<', 'minimum_stock_level')
            ->paginate($perPage);

        return $this->paginated(IngredientResource::collection($ingredients)->resource);
    }
}

