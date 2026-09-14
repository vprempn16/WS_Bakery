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

        if (isset($data['paymentStatus'])) {
            $data['paymentStatus'] = strtolower((string) $data['paymentStatus']);
        }
        if (isset($data['paymentMethod'])) {
            $data['paymentMethod'] = strtolower((string) $data['paymentMethod']);
        }

        // DetailView omits displaytype=2 money/timestamp fields; always expose them for receipts/POS.
        $data['subTotal'] = (float) ($this->sub_total ?? 0);
        $data['discountAmount'] = (float) ($this->discount_amount ?? 0);
        $data['taxAmount'] = (float) ($this->tax_amount ?? 0);
        $data['grandTotal'] = (float) ($this->grand_total ?? 0);
        $data['createdAt'] = $this->created_at
            ? $this->created_at->format('Y-m-d H:i:s')
            : ($data['createdAt'] ?? null);
        
        $data['itemCount'] = $this->when(
            isset($this->items_count) || $this->relationLoaded('items'),
            fn () => $this->items_count ?? $this->items->count()
        );

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
        });

        return $data;
    }
}
