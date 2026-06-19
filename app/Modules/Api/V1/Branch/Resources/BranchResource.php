<?php

namespace App\Modules\Api\V1\Branch\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'organizationId_label' => $this->organization ? $this->organization->name : null,
            'name' => $this->name,
            'type' => $this->type,
            'address' => $this->address,
            'phone' => $this->phone,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
