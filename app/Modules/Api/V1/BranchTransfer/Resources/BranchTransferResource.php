<?php

namespace App\Modules\Api\V1\BranchTransfer\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BranchTransferResource extends JsonResource
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
            'transferNumber' => $this->transfer_number,
            'quantity' => (float) $this->quantity,
            'transferDate' => $this->transfer_date ? $this->transfer_date->format('Y-m-d') : null,
            'status' => $this->status,
            'notes' => $this->notes,
            'createdAt' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
