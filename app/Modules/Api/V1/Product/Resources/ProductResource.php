<?php

namespace App\Modules\Api\V1\Product\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'organizationId_label' => $this->organization ? $this->organization->name : null,
            'productNumber' => $this->product_number,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'unit' => $this->unit,
            'shelfLifeDays' => $this->shelf_life_days,
            'shelfLifeHours' => $this->shelf_life_hours,
            'tier' => $this->tier,
            'currentStock' => (float) $this->current_stock,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
