<?php

namespace App\Modules\Api\V1\ProductionBatch\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductionBatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'batchNumber' => $this->batch_number,
            'productId' => $this->product_id,
            'quantityProduced' => (float) $this->quantity_produced,
            'productionDate' => $this->production_date ? $this->production_date->format('Y-m-d') : null,
            'expiryDate' => $this->expiry_date ? $this->expiry_date->format('Y-m-d') : null,
            'status' => $this->status,
            'notes' => $this->notes,
            'createdAt' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
