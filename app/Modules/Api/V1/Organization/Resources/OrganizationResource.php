<?php

namespace App\Modules\Api\V1\Organization\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    public static $wrap = 'data';

    public function toArray(Request $request): array
    {
        return [
            'values' => [
                'id' => $this->id,
                'name' => $this->name,
                'description' => $this->description,
                'email' => $this->email,
                'phone' => $this->phone,
                'address' => $this->address,
            ]
        ];
    }
}
