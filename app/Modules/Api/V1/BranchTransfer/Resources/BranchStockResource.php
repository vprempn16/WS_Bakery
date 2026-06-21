<?php

namespace App\Modules\Api\V1\BranchTransfer\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BranchStockResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'branchId' => $this->branch_id,
            'branchId_label' => $this->branch ? $this->branch->name : null,
            'productId' => $this->product_id,
            'productId_label' => $this->product ? $this->product->name : null,
            'currentStock' => (float) $this->current_stock,
            'updatedAt' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
