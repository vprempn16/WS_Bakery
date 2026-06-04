<?php

namespace App\Modules\Api\V1\User\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public static $wrap = 'data';

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
            'branchId' => $this->branch_id,
        ];

        if ($this->token) {
            $data['token'] = $this->token;
        }

        return [
            'values' => $data
        ];
    }
}
