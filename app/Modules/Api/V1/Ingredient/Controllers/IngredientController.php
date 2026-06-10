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
        $ingredients = Ingredient::where('organization_id', $orgId)->get();

        return IngredientResource::collection($ingredients);
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

        return new IngredientResource($ingredient);
    }

    public function show(Request $request, $id)
    {
        $orgId = $request->user()->organization_id;
        $ingredient = Ingredient::where('organization_id', $orgId)->findOrFail($id);
        return new IngredientResource($ingredient);
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

        return new IngredientResource($ingredient);
    }

    public function destroy(Request $request, $id)
    {
        $orgId = $request->user()->organization_id;
        $ingredient = Ingredient::where('organization_id', $orgId)->findOrFail($id);
        $ingredient->delete();

        return response()->json([
            'message' => 'Ingredient successfully deleted.'
        ]);
    }

    public function lowStock(Request $request)
    {
        $orgId = $request->user()->organization_id;
        $ingredients = Ingredient::where('organization_id', $orgId)
            ->whereColumn('current_stock', '<', 'minimum_stock_level')
            ->get();

        return IngredientResource::collection($ingredients);
    }
}

