<?php

namespace App\Modules\Api\V1\Recipe\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Modules\Api\V1\Ingredient\Resources\IngredientResource;
use App\Modules\Api\V1\Recipe\Models\Recipe;

class RecipeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pending = $this->resource instanceof Recipe
            ? $this->resource->isPending()
            : (bool) ($this->quantity_pending ?? false);

        return [
            'id' => $this->id,
            'productId' => $this->product_id,
            'productId_label' => $this->product ? $this->product->name : null,
            'ingredientId' => $this->ingredient_id,
            'ingredientId_label' => $this->ingredient ? $this->ingredient->name : null,
            'unit' => $this->ingredient ? $this->ingredient->unit : null,
            'quantityRequired' => $pending ? null : ($this->quantity_required !== null ? (float) $this->quantity_required : null),
            'quantityPending' => $pending,
            'ingredient' => $this->whenLoaded('ingredient', function () {
                return new IngredientResource($this->ingredient);
            }),
        ];
    }
}
