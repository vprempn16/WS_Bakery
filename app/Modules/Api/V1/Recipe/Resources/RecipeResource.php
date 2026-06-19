<?php

namespace App\Modules\Api\V1\Recipe\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Modules\Api\V1\Ingredient\Resources\IngredientResource;

class RecipeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'productId' => $this->product_id,
            'productId_label' => $this->product ? $this->product->name : null,
            'ingredientId' => $this->ingredient_id,
            'ingredientId_label' => $this->ingredient ? $this->ingredient->name : null,
            'quantityRequired' => (float) $this->quantity_required,
            'ingredient' => $this->whenLoaded('ingredient', function() {
                return new IngredientResource($this->ingredient);
            }),
        ];
    }
}
