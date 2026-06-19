<?php

namespace App\Modules\Api\V1\Ingredient\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IngredientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'organizationId_label' => $this->organization ? $this->organization->name : null,
            'vendorId' => $this->vendor_id,
            'vendorId_label' => $this->vendor ? $this->vendor->name : null,
            'name' => $this->name,
            'unit' => $this->unit,
            'minimumStockLevel' => (float) $this->minimum_stock_level,
            'currentStock' => (float) $this->current_stock,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
