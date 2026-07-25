<?php

namespace App\Modules\Api\V1\User\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Modules\Api\V1\Organization\Models\Organization;

class User extends Authenticatable
{
    use \App\Traits\Auditable;
    use HasApiTokens, HasFactory, Notifiable, HasUuids;

    protected $fillable = [
        'organization_id',
        'branch_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'role',
        'is_active',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch()
    {
        return $this->belongsTo(\App\Modules\Api\V1\Branch\Models\Branch::class);
    }

    public function getAllowedModules()
    {
        $allModules = [
            ['value' => 'vendor', 'label' => 'Vendor'],
            ['value' => 'ingredient', 'label' => 'Ingredient'],
            ['value' => 'product', 'label' => 'Product'],
            ['value' => 'branch', 'label' => 'Branch'],
            ['value' => 'user', 'label' => 'User'],
            ['value' => 'billing', 'label' => 'Billing'],
            ['value' => 'branchdailyreport', 'label' => 'Branch Daily Report'],
            ['value' => 'branchtransfer', 'label' => 'Branch Transfer'],
            ['value' => 'branchstock', 'label' => 'Branch Stock'],
            ['value' => 'inventorytransaction', 'label' => 'Inventory Transaction'],
            ['value' => 'productionbatch', 'label' => 'Production Batch'],
            ['value' => 'recipe', 'label' => 'Recipe'],
        ];

        $role = strtolower((string) ($this->role ?? ''));

        // Org admin / platform superadmin: every module + all branch data
        if (in_array($role, ['admin', 'superadmin'], true)) {
            return $allModules;
        }

        if (in_array($role, ['warehouse', 'warehouse_manager', 'manager'], true)) {
            $warehouseModules = [
                'product', 'ingredient', 'branchtransfer', 'branchstock',
                'inventorytransaction', 'productionbatch', 'recipe', 'vendor', 'branch',
            ];

            return array_values(array_filter($allModules, function ($module) use ($warehouseModules) {
                return in_array($module['value'], $warehouseModules, true);
            }));
        }

        // Branch staff: POS + daily report + products for their branch
        $branchModules = ['billing', 'branchdailyreport', 'product', 'branchstock'];

        return array_values(array_filter($allModules, function ($module) use ($branchModules) {
            return in_array($module['value'], $branchModules, true);
        }));
    }

    /**
     * Unrestricted roles: org admin (client) or platform superadmin (developer).
     * Legacy "owner" is treated as admin until data is migrated.
     */
    public function isFullAdmin(): bool
    {
        $role = strtolower((string) ($this->role ?? ''));

        return in_array($role, ['admin', 'superadmin', 'owner'], true);
    }

    /** @deprecated Use isFullAdmin() — kept for older call sites */
    public function isOwner(): bool
    {
        return $this->isFullAdmin();
    }

    public function isSuperAdmin(): bool
    {
        return strtolower((string) ($this->role ?? '')) === 'superadmin';
    }

    public function adminRoleLabel(): string
    {
        return $this->isSuperAdmin() ? 'Super Admin' : 'Admin';
    }
}
