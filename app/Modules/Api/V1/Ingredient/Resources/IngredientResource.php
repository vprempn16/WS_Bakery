<?php

namespace App\Modules\Api\V1\Ingredient\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IngredientResource extends JsonResource
{
    public static $wrap = 'data';

    public function toArray(Request $request): array
    {
        return [
            'values' => [
                'id' => $this->id,
                'organizationId' => $this->organization_id,
                'vendorId' => $this->vendor_id,
                'name' => $this->name,
                'unit' => $this->unit,
                'minimumStockLevel' => (float) $this->minimum_stock_level,
                'currentStock' => (float) $this->current_stock,
                'createdAt' => $this->created_at,
                'updatedAt' => $this->updated_at,
            ]
        ];
    }
}
