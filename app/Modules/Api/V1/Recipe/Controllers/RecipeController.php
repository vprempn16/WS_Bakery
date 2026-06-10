<?php

namespace App\Modules\Api\V1\Recipe\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\Recipe\Models\Recipe;
use App\Modules\Api\V1\Recipe\Requests\StoreRecipeRequest;
use App\Modules\Api\V1\Recipe\Resources\RecipeResource;

class RecipeController extends Controller
{
    public function index($productId)
    {
        $product = Product::findOrFail($productId);
        $recipes = $product->recipes()->with('ingredient')->get();

        return RecipeResource::collection($recipes);
    }

    public function store(StoreRecipeRequest $request, $productId)
    {
        $product = Product::findOrFail($productId);
        $values = $request->input('data.values');

        $recipe = Recipe::updateOrCreate(
            ['product_id' => $product->id, 'ingredient_id' => $values['ingredientId']],
            ['quantity_required' => $values['quantityRequired']]
        );

        $recipe->load('ingredient');

        return new RecipeResource($recipe);
    }

    public function destroy($productId, $ingredientId)
    {
        Recipe::where('product_id', $productId)
              ->where('ingredient_id', $ingredientId)
              ->delete();

        return response()->json([
            'message' => 'Recipe ingredient successfully removed.'
        ]);
    }
}
