<?php

namespace App\Modules\Api\V1\Billing\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branchId' => $this->branch_id,
            'branchId_label' => $this->branch ? $this->branch->name : null,
            'billNumber' => $this->bill_number,
            'customerName' => $this->customer_name,
            'customerPhone' => $this->customer_phone,
            'customerEmail' => $this->customer_email,
            'subTotal' => (float) $this->sub_total,
            'discountAmount' => (float) $this->discount_amount,
            'taxAmount' => (float) $this->tax_amount,
            'grandTotal' => (float) $this->grand_total,
            'paymentMethod' => $this->payment_method,
            'paymentStatus' => $this->payment_status,
            'billingDate' => $this->billing_date ? $this->billing_date->format('Y-m-d H:i:s') : null,
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'productId' => $item->product_id,
                        'productId_label' => $item->product ? $item->product->name : null,
                        'quantity' => (float) $item->quantity,
                        'unitPrice' => (float) $item->unit_price,
                        'totalPrice' => (float) $item->total_price,
                    ];
                });
            }),
            'createdAt' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
