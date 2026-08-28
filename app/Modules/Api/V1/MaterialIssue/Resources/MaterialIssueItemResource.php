<?php

namespace App\Modules\Api\V1\MaterialIssue\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MaterialIssueItemResource extends JsonResource
{
    public function toArray($request)
    {
        $ingredient = $this->relationLoaded('ingredient') ? $this->ingredient : null;

        return [
            'id' => $this->id,
            'ingredientId' => $this->ingredient_id,
            'ingredientId_label' => $ingredient?->name,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit ?? $ingredient?->unit,
            'currentStock' => $ingredient ? (float) $ingredient->current_stock : null,
        ];
    }
}
