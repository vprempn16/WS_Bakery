<?php

namespace App\Modules\Api\V1\SalesReturn\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SalesReturnResource extends JsonResource
{
    public function toArray($request)
    {
        $branchLabel = null;
        if ($this->relationLoaded('branch') && $this->branch) {
            $branchLabel = $this->branch->name;
        }

        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'returnNumber' => $this->return_number,
            'branchId' => $this->branch_id,
            'branchId_label' => $branchLabel,
            'returnDate' => $this->return_date ? $this->return_date->format('Y-m-d') : null,
            'totalReturnValue' => (float) $this->total_return_value,
            'notes' => $this->notes,
            'itemCount' => $this->when(
                isset($this->items_count) || $this->relationLoaded('items'),
                fn () => $this->items_count ?? $this->items->count()
            ),
            'items' => SalesReturnItemResource::collection($this->whenLoaded('items')),
            'createdBy' => $this->created_by,
            'createdAt' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updatedAt' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
