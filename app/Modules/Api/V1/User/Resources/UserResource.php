<?php

namespace App\Modules\Api\V1\User\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

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
        $isActive = (int) ($this->is_active ?? 1) === 1 ? 1 : 0;

        $roleRel = DB::table('role_user_rel')
            ->where('user_id', $this->id)
            ->where('organization_id', $this->organization_id)
            ->first();

        $settingsRole = null;
        if ($roleRel?->role_id) {
            $settingsRole = DB::table('roles')
                ->where('id', $roleRel->role_id)
                ->where('organization_id', $this->organization_id)
                ->where('deleted', 0)
                ->first();
        }

        $data = [
            'id' => $this->id,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'phoneNumber' => $this->phone,
            // Bakery access key (admin / superadmin / staff). Full admins never use Settings Role.
            'role' => $this->role,
            'roleId' => $settingsRole?->id,
            'roleId_label' => $settingsRole?->name,
            // is_admin = full admin flag; status/is_active = account active (checkbox).
            'is_admin' => $this->resource->isFullAdmin(),
            'is_active' => $isActive,
            'status' => $isActive,
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
