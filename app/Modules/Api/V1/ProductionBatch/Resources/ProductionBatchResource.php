<?php

namespace App\Modules\Api\V1\ProductionBatch\Resources;

use App\Services\ShelfLifeStatusService;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionBatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        $expiry = $this->expiry_timestamp;
        $productUnit = $this->product?->unit;
        $shelf = ShelfLifeStatusService::statusForTimestamp($expiry);

        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'organizationId_label' => $this->organization ? $this->organization->name : null,
            'batchNumber' => $this->batch_number,
            'productId' => $this->product_id,
            'productId_label' => $this->product
                ? trim(($this->product->name ?? '') . ($this->product->product_number ? ' (#' . $this->product->product_number . ')' : ''))
                : null,
            'productUnit' => $productUnit,
            'quantityProduced' => (float) $this->quantity_produced,
            'pieces' => $this->pieces !== null ? (int) $this->pieces : null,
            'productionDate' => $this->production_date ? $this->production_date->format('Y-m-d H:i:s') : null,
            'expiryDate' => $expiry ? $expiry->format('Y-m-d') : null,
            'expiryTime' => $expiry ? $expiry->format('g:i a') : null,
            'expiryTimestamp' => $expiry ? $expiry->format('Y-m-d H:i:s') : null,
            'shelfStatus' => $shelf['shelfStatus'],
            'earliestExpiry' => $shelf['earliestExpiry'],
            'status' => $this->status,
            'notes' => $this->notes,
            'createdAt' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
