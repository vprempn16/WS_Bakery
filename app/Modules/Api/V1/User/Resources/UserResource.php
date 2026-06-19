<?php

namespace App\Modules\Api\V1\User\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    protected ?string $token = null;

    public function __construct($resource, ?string $token = null)
    {
        parent::__construct($resource);
        $this->token = $token;
    }

    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'organizationId' => $this->organization_id,
            'organizationId_label' => $this->organization ? $this->organization->name : null,
            'branchId' => $this->branch_id,
            'branchId_label' => $this->branch ? $this->branch->name : null,
        ];

        if ($this->token) {
            $data['token'] = $this->token;
        }

        return $data;
    }
}
