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
            'ingredientId' => $this->ingredient_id,
            'quantityRequired' => (float) $this->quantity_required,
            'ingredient' => $this->whenLoaded('ingredient', function() {
                return new IngredientResource($this->ingredient);
            }),
        ];
    }
}
