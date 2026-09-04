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

        // Computed shelf warning (not a DB column) — badge on Product list/detail
        if (array_key_exists('shelf_status_computed', $this->resource->getAttributes())
            || isset($this->resource->shelf_status_computed)
        ) {
            $data['shelfStatus'] = $this->resource->shelf_status_computed;
        }
        if (array_key_exists('earliest_expiry_computed', $this->resource->getAttributes())
            || isset($this->resource->earliest_expiry_computed)
        ) {
            $data['earliestExpiry'] = $this->resource->earliest_expiry_computed;
        }

        return $data;
    }
}
