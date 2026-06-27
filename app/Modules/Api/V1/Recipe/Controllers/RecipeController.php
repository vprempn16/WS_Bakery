<?php

namespace App\Modules\Api\V1\Recipe\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\Recipe\Models\Recipe;
use App\Modules\Api\V1\Recipe\Requests\StoreRecipeRequest;
use App\Modules\Api\V1\Recipe\Resources\RecipeResource;

class RecipeController extends Controller
{
    public function index(Request $request, $productId)
    {
        $perPage = $request->query('per_page', 20);
        $product = Product::findOrFail($productId);
        $recipes = $product->recipes()->with('ingredient')->paginate($perPage);

        $fieldList = \App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig::getMappedFields('Recipe');

        return $this->paginated(RecipeResource::collection($recipes)->resource, $fieldList);
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

        return $this->success(new RecipeResource($recipe), 'Recipe ingredient added successfully.', 201);
    }

    public function show($productId, $ingredientId)
    {
        try {
            $recipe = Recipe::where('product_id', $productId)
                ->where('ingredient_id', $ingredientId)
                ->with('ingredient')
                ->firstOrFail();
            
            $resource = new RecipeResource($recipe);
            
            $fieldList = \App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig::getMappedFields('Recipe');
            
            return $this->success([
                'fields' => $fieldList,
                'values' => $resource->toArray(request())
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Recipe not found.', null, null, null, 404);
        }
    }

    public function destroy($productId, $ingredientId)
    {
        try {
            $deleted = Recipe::where('product_id', $productId)
                  ->where('ingredient_id', $ingredientId)
                  ->delete();

            if (!$deleted) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
            }

            return $this->success(null, 'Recipe ingredient successfully removed.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Recipe not found.', null, null, null, 404);
        }
    }
}
