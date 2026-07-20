<?php

namespace App\Modules\Api\V1\BranchTransfer\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BranchTransferItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'productId' => $this->product_id,
            'productId_label' => $this->product ? $this->product->name : null,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'pieces' => $this->pieces !== null ? (float) $this->pieces : null,
            'category' => $this->product ? $this->product->category : null,
        ];
    }
}
