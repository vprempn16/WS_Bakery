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
        if (array_key_exists('expired_qty_computed', $this->resource->getAttributes())
            || isset($this->resource->expired_qty_computed)
        ) {
            $data['expiredQty'] = (float) $this->resource->expired_qty_computed;
        }
        if (array_key_exists('has_fresh_lot_computed', $this->resource->getAttributes())
            || isset($this->resource->has_fresh_lot_computed)
        ) {
            $data['hasFreshLot'] = (bool) $this->resource->has_fresh_lot_computed;
        }
        if (array_key_exists('warehouse_stock_computed', $this->resource->getAttributes())
            || isset($this->resource->warehouse_stock_computed)
        ) {
            $data['warehouseStock'] = (float) $this->resource->warehouse_stock_computed;
        }

        return $data;
    }
}
