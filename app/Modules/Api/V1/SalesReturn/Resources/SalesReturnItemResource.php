<?php

namespace App\Modules\Api\V1\SalesReturn\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SalesReturnItemResource extends JsonResource
{
    public function toArray($request)
    {
        $product = $this->relationLoaded('product') ? $this->product : null;

        return [
            'id' => $this->id,
            'productId' => $this->product_id,
            'productId_label' => $product?->name,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit ?? $product?->unit,
            'pieces' => $this->pieces !== null ? (float) $this->pieces : null,
            'unitPrice' => (float) $this->unit_price,
            'returnValue' => (float) $this->return_value,
        ];
    }
}
