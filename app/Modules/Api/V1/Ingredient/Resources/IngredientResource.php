<?php

namespace App\Modules\Api\V1\Ingredient\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IngredientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource->transformToApiFormat();
        $data['organizationId_label'] = $this->organization ? $this->organization->name : null;
        $data['vendorId_label'] = $this->vendor ? $this->vendor->name : null;
        return $data;
    }
}
