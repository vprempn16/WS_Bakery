<?php

namespace App\Modules\Api\V1\ProductStockTransaction\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductStockTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'organizationId_label' => $this->organization ? $this->organization->name : null,
            'productId' => $this->product_id,
            'productId_label' => $this->product ? $this->product->name : null,
            'unit' => $this->product ? $this->product->unit : null,
            'type' => $this->type,
            'quantity' => (float) $this->quantity,
            'referenceNote' => $this->reference_note,
            'createdAt' => $this->created_at,
        ];
    }
}
