<?php

namespace App\Modules\Api\V1\BranchSales\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BranchDailyReportItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'productId' => $this->product_id,
            'productId_label' => $this->product ? $this->product->name : null,
            'quantitySold' => (float) $this->quantity_sold,
            'quantityReturned' => (float) $this->quantity_returned,
            'unitPrice' => (float) $this->unit_price,
            'subtotalRevenue' => (float) $this->subtotal_revenue,
            'subtotalWaste' => (float) $this->subtotal_waste,
        ];
    }
}
