<?php

namespace App\Modules\Api\V1\User\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Modules\Api\V1\Organization\Models\Organization;
use Illuminate\Support\Facades\DB;

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

        if (in_array($role, ['admin', 'superadmin'], true)) {
            return $allModules;
        }

        // Prefer modules from assigned Settings Roles → Profiles
        $fromProfiles = $this->modulesFromAssignedRoles();
        if ($fromProfiles !== null) {
            return array_values(array_filter($allModules, function ($module) use ($fromProfiles) {
                return in_array($module['value'], $fromProfiles, true);
            }));
        }

        // Legacy fallback by users.role string
        if (in_array($role, ['warehouse', 'warehouse_manager', 'manager'], true)) {
            $warehouseModules = [
                'product', 'ingredient', 'branchtransfer', 'branchstock',
                'inventorytransaction', 'productionbatch', 'recipe', 'vendor', 'branch',
            ];

            return array_values(array_filter($allModules, function ($module) use ($warehouseModules) {
                return in_array($module['value'], $warehouseModules, true);
            }));
        }

        $branchModules = ['billing', 'branchdailyreport', 'product', 'branchstock'];

        return array_values(array_filter($allModules, function ($module) use ($branchModules) {
            return in_array($module['value'], $branchModules, true);
        }));
    }

    /**
     * @return list<string>|null  lowercase module values with view permission, or null if no roles
     */
    private function modulesFromAssignedRoles(): ?array
    {
        $orgId = $this->organization_id;
        if (!$orgId) {
            return null;
        }

        $roleIds = DB::table('roles')
            ->join('role_user_rel', 'roles.id', '=', 'role_user_rel.role_id')
            ->where('role_user_rel.user_id', $this->id)
            ->where('role_user_rel.organization_id', $orgId)
            ->where('roles.deleted', 0)
            ->pluck('roles.id')
            ->unique()
            ->filter();

        if ($roleIds->isEmpty()) {
            return null;
        }

        $profileIds = DB::table('role_profile_rel')
            ->whereIn('role_id', $roleIds)
            ->where('organization_id', $orgId)
            ->pluck('profile_id')
            ->unique()
            ->filter();

        if ($profileIds->isEmpty()) {
            return null;
        }

        $allowed = [];
        foreach ($profileIds as $profileId) {
            $profileFile = base_path("Profiles/{$orgId}/{$profileId}_Profile.php");
            if (!file_exists($profileFile)) {
                continue;
            }
            $data = include $profileFile;
            if (!is_array($data) || !isset($data['modules']) || !is_array($data['modules'])) {
                continue;
            }
            foreach ($data['modules'] as $moduleName => $modData) {
                $view = (int) ($modData['permissions']['view'] ?? 0);
                if ($view === 1) {
                    $allowed[] = strtolower((string) $moduleName);
                }
            }
        }

        return $allowed === [] ? null : array_values(array_unique($allowed));
    }

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
