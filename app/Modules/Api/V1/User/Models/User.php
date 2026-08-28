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
        $fullActions = ['view' => 1, 'create' => 1, 'edit' => 1, 'delete' => 1];

        if (in_array($role, ['admin', 'superadmin', 'owner'], true)) {
            return array_map(function (array $module) use ($fullActions) {
                $module['actions'] = $fullActions;

                return $module;
            }, $allModules);
        }

        // Prefer modules + actions from assigned Settings Roles → Profiles
        $fromProfiles = $this->moduleActionsFromAssignedRoles();
        if ($fromProfiles !== null) {
            return array_values(array_filter(array_map(function ($module) use ($fromProfiles) {
                $key = $module['value'];
                if (! isset($fromProfiles[$key])) {
                    return null;
                }
                $module['actions'] = $fromProfiles[$key];

                return $module;
            }, $allModules)));
        }

        // Legacy fallback by users.role string
        if (in_array($role, ['warehouse', 'warehouse_manager', 'manager'], true)) {
            $warehouseActions = [
                'product' => ['view' => 1, 'create' => 1, 'edit' => 1, 'delete' => 0],
                'ingredient' => ['view' => 1, 'create' => 1, 'edit' => 1, 'delete' => 0],
                'branchtransfer' => ['view' => 1, 'create' => 1, 'edit' => 1, 'delete' => 0],
                'branchstock' => ['view' => 1, 'create' => 0, 'edit' => 0, 'delete' => 0],
                'inventorytransaction' => ['view' => 1, 'create' => 1, 'edit' => 1, 'delete' => 0],
                'productionbatch' => ['view' => 1, 'create' => 1, 'edit' => 1, 'delete' => 0],
                'recipe' => ['view' => 1, 'create' => 1, 'edit' => 1, 'delete' => 0],
                'vendor' => ['view' => 1, 'create' => 1, 'edit' => 1, 'delete' => 0],
                'branch' => ['view' => 1, 'create' => 0, 'edit' => 0, 'delete' => 0],
            ];

            return array_values(array_filter(array_map(function ($module) use ($warehouseActions) {
                $key = $module['value'];
                if (! isset($warehouseActions[$key])) {
                    return null;
                }
                $module['actions'] = $warehouseActions[$key];

                return $module;
            }, $allModules)));
        }

        // Legacy branch staff fallback mirrors the default Sales Staff profile.
        // BranchTransfer is view-only here; receiving is authorized separately
        // as a destination-branch workflow action.
        $branchActions = [
            'billing' => ['view' => 1, 'create' => 1, 'edit' => 1, 'delete' => 0],
            'branchdailyreport' => ['view' => 1, 'create' => 1, 'edit' => 0, 'delete' => 0],
            'product' => ['view' => 1, 'create' => 0, 'edit' => 0, 'delete' => 0],
            'branchstock' => ['view' => 1, 'create' => 0, 'edit' => 0, 'delete' => 0],
            'branchtransfer' => ['view' => 1, 'create' => 0, 'edit' => 0, 'delete' => 0],
        ];

        return array_values(array_filter(array_map(function ($module) use ($branchActions) {
            $key = $module['value'];
            if (! isset($branchActions[$key])) {
                return null;
            }
            $module['actions'] = $branchActions[$key];

            return $module;
        }, $allModules)));
    }

    /**
     * @return array<string, array{view:int, create:int, edit:int, delete:int}>|null
     *         lowercase module value => action bits, or null if no roles/profiles
     */
    private function moduleActionsFromAssignedRoles(): ?array
    {
        $orgId = $this->organization_id;
        if (! $orgId) {
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

        $merged = [];
        foreach ($profileIds as $profileId) {
            $profileFile = base_path("Profiles/{$orgId}/{$profileId}_Profile.php");
            if (! file_exists($profileFile)) {
                continue;
            }
            $data = include $profileFile;
            if (! is_array($data) || ! isset($data['modules']) || ! is_array($data['modules'])) {
                continue;
            }
            foreach ($data['modules'] as $moduleName => $modData) {
                $perms = $modData['permissions'] ?? [];
                $view = (int) ($perms['view'] ?? 0);
                if ($view !== 1) {
                    continue;
                }
                $key = strtolower((string) $moduleName);
                $incoming = [
                    'view' => 1,
                    'create' => (int) ($perms['create'] ?? 0) === 1 ? 1 : 0,
                    'edit' => (int) ($perms['edit'] ?? 0) === 1 ? 1 : 0,
                    'delete' => (int) ($perms['delete'] ?? 0) === 1 ? 1 : 0,
                ];
                if (! isset($merged[$key])) {
                    $merged[$key] = $incoming;
                    continue;
                }
                foreach (['view', 'create', 'edit', 'delete'] as $action) {
                    $merged[$key][$action] = max((int) $merged[$key][$action], (int) $incoming[$action]);
                }
            }
        }

        return $merged === [] ? null : $merged;
    }

    /**
     * @deprecated Prefer moduleActionsFromAssignedRoles via getAllowedModules() actions.
     * @return list<string>|null  lowercase module values with view permission, or null if no roles
     */
    private function modulesFromAssignedRoles(): ?array
    {
        $actions = $this->moduleActionsFromAssignedRoles();
        if ($actions === null) {
            return null;
        }

        return array_keys($actions);
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
