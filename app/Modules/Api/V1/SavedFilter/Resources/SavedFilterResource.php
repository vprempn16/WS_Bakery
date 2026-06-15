<?php

namespace App\Modules\Api\V1\SavedFilter\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavedFilterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'userId' => $this->user_id,
            'name' => $this->name,
            'module' => $this->module,
            'rules' => $this->rules,
            'isPublic' => $this->is_public,
            'isDefault' => $this->is_default,
            'headerDetails' => $this->header_details,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
