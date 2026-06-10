<?php

namespace App\Modules\Api\V1\InventoryTransaction\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryTransactionResource extends JsonResource
{
    public static $wrap = 'data';

    public function toArray(Request $request): array
    {
        return [
            'values' => [
                'id' => $this->id,
                'organizationId' => $this->organization_id,
                'ingredientId' => $this->ingredient_id,
                'type' => $this->type,
                'quantity' => (float) $this->quantity,
                'referenceNote' => $this->reference_note,
                'createdAt' => $this->created_at,
            ]
        ];
    }
}
