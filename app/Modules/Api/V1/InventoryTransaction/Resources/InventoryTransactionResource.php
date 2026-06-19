<?php

namespace App\Modules\Api\V1\InventoryTransaction\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'organizationId_label' => $this->organization ? $this->organization->name : null,
            'ingredientId' => $this->ingredient_id,
            'ingredientId_label' => $this->ingredient ? $this->ingredient->name : null,
            'type' => $this->type,
            'quantity' => (float) $this->quantity,
            'referenceNote' => $this->reference_note,
            'createdAt' => $this->created_at,
        ];
    }
}
