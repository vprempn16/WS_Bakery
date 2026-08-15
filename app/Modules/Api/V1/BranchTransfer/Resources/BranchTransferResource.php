<?php

namespace App\Modules\Api\V1\BranchTransfer\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BranchTransferResource extends JsonResource
{
    public function toArray($request)
    {
        $createdByLabel = null;
        if ($this->created_by) {
            $user = $this->relationLoaded('creator')
                ? $this->creator
                : \App\Modules\Api\V1\User\Models\User::find($this->created_by);
            if ($user) {
                $createdByLabel = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->email;
            }
        }

        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'branchId' => $this->branch_id,
            'branchId_label' => $this->branch ? $this->branch->name : null,
            'transferNumber' => $this->transfer_number,
            'transferDate' => $this->transfer_date ? $this->transfer_date->format('Y-m-d') : null,
            'status' => $this->status,
            'notes' => $this->notes,
            'createdBy' => $createdByLabel ?: $this->created_by,
            'createdBy_label' => $createdByLabel,
            'itemCount' => $this->when(
                isset($this->items_count) || $this->relationLoaded('items'),
                fn () => $this->items_count ?? $this->items->count()
            ),
            'items' => BranchTransferItemResource::collection($this->whenLoaded('items')),
            'createdAt' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
