<?php

namespace App\Modules\Api\V1\Product\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource->transformToApiFormat();
        $data['organizationId_label'] = $this->organization ? $this->organization->name : null;
        return $data;
    }
}
