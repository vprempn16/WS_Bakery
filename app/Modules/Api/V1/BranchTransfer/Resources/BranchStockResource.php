<?php

namespace App\Modules\Api\V1\BranchTransfer\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BranchStockResource extends JsonResource
{
    public function toArray($request)
    {
        $updatedAt = $this->updated_at;

        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'branchId' => $this->branch_id,
            'branchId_label' => $this->branch ? $this->branch->name : null,
            'productId' => $this->product_id,
            'productId_label' => $this->product ? $this->product->name : null,
            // Product's configured unit for list display (e.g. "100 pcs", "1 kg").
            'unit' => $this->product ? $this->product->unit : null,
            'currentStock' => (float) $this->current_stock,
            'createdAt' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updatedAt' => $updatedAt ? $updatedAt->format('Y-m-d H:i:s') : null,
            // Split for BranchStock list columns (date filter scopes on updated_at).
            'updatedDate' => $updatedAt ? $updatedAt->format('Y-m-d') : null,
            'updatedTime' => $updatedAt ? $updatedAt->format('H:i') : null,
        ];
    }
}
