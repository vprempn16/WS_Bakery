<?php

namespace App\Modules\Api\V1\ProductionPlan\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductionPlanResource extends JsonResource
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
            'planDate' => $this->plan_date ? $this->plan_date->format('Y-m-d') : null,
            'status' => $this->status,
            'notes' => $this->notes,
            'createdBy' => $createdByLabel ?: $this->created_by,
            'createdBy_label' => $createdByLabel,
            'itemCount' => $this->when(
                isset($this->items_count) || $this->relationLoaded('items'),
                fn () => $this->items_count ?? $this->items->count()
            ),
            'items' => ProductionPlanItemResource::collection($this->whenLoaded('items')),
            'createdAt' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updatedAt' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
