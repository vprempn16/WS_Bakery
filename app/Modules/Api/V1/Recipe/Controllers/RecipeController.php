<?php

namespace App\Modules\Api\V1\Recipe\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\Recipe\Models\Recipe;
use App\Modules\Api\V1\Recipe\Requests\StoreRecipeRequest;
use App\Modules\Api\V1\Recipe\Resources\RecipeResource;
use App\Services\AuthUser;
use App\Services\CRM\RecordObject;
use App\Services\PermissionService;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
    public function index(Request $request, $productId)
    {
        $user = AuthUser::requireUser();
        $permissionService = new PermissionService($user);
        if (!$permissionService->hasPermission('Recipe', 'view') && !$permissionService->hasPermission('Product', 'view')) {
            return $this->error("You don't have permission to view recipes.", null, null, null, 403);
        }

        try {
            RecordObject::make('Product', $productId, [], 'DetailView');
        } catch (\Exception $e) {
            return $this->error('Product not found or access denied.', null, null, null, 404);
        }

        $product = Product::findOrFail($productId);
        $perPage = $request->query('per_page', 20);
        $recipes = $product->recipes()->with('ingredient')->paginate($perPage);

        $fieldList = \App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig::getApiFieldsForView('Recipe', 'DetailView');

        return $this->paginated(RecipeResource::collection($recipes)->resource, $fieldList);
    }

    public function store(StoreRecipeRequest $request, $productId)
    {
        $user = AuthUser::requireUser();
        $permissionService = new PermissionService($user);
        if (!$permissionService->hasPermission('Recipe', 'create') && !$permissionService->hasPermission('Product', 'edit')) {
            return $this->error("You don't have permission to edit recipes.", null, null, null, 403);
        }

        try {
            RecordObject::make('Product', $productId, [], 'EditView');
        } catch (\Exception $e) {
            return $this->error('Product not found or access denied.', null, null, null, 404);
        }

        $product = Product::findOrFail($productId);
        if ($product->isBought()) {
            return $this->error(
                'Cannot add ingredients: this is a bought (outside brand) product. Bought products are sold as-is without a recipe.',
                null,
                null,
                null,
                400
            );
        }
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
            RecordObject::make('Product', $productId, [], 'DetailView');

            $recipe = Recipe::where('product_id', $productId)
                ->where('ingredient_id', $ingredientId)
                ->with('ingredient')
                ->firstOrFail();

            $resource = new RecipeResource($recipe);

            $fieldList = \App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig::getApiFieldsForView('Recipe', 'DetailView');

            return $this->success([
                'fields' => $fieldList,
                'values' => $resource->toArray(request()),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Recipe not found.', null, null, null, 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
        }
    }

    public function destroy($productId, $ingredientId)
    {
        $user = AuthUser::requireUser();
        $permissionService = new PermissionService($user);
        if (!$permissionService->hasPermission('Recipe', 'delete') && !$permissionService->hasPermission('Product', 'edit')) {
            return $this->error("You don't have permission to remove recipe ingredients.", null, null, null, 403);
        }

        try {
            RecordObject::make('Product', $productId, [], 'EditView');

            $product = Product::findOrFail($productId);
            if ($product->isBought()) {
                return $this->error(
                    'Cannot change recipe: this is a bought (outside brand) product.',
                    null,
                    null,
                    null,
                    400
                );
            }

            $deleted = Recipe::where('product_id', $productId)
                ->where('ingredient_id', $ingredientId)
                ->delete();

            if (!$deleted) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
            }

            return $this->success(null, 'Recipe ingredient successfully removed.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Recipe not found.', null, null, null, 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
        }
    }
}
