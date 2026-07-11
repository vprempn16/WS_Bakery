<?php

namespace App\Modules\Api\V1\Billing\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource->transformToApiFormat();
        $data['branchId_label'] = $this->branch ? $this->branch->name : null;
        
        $data['items'] = $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'productId' => $item->product_id,
                        'productId_label' => $item->product ? $item->product->name : null,
                        'quantity' => (float) $item->quantity,
                        'unitPrice' => (float) $item->unit_price,
                        'totalPrice' => (float) $item->total_price,
                        'unit' => $item->unit,
                        'category' => $item->category,
                    ];
                });
            }),
        return $data;
    }
}
