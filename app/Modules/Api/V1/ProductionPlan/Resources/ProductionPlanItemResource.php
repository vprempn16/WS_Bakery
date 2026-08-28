<?php

namespace App\Modules\Api\V1\ProductionPlan\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductionPlanItemResource extends JsonResource
{
    public function toArray($request)
    {
        $product = $this->relationLoaded('product') ? $this->product : null;

        return [
            'id' => $this->id,
            'productId' => $this->product_id,
            'productId_label' => $product?->name,
            'plannedQuantity' => (float) $this->planned_quantity,
            'producedQuantity' => $this->produced_quantity !== null ? (float) $this->produced_quantity : null,
            'unit' => $product?->unit,
        ];
    }
}
