<?php

namespace App\Services;

use App\Modules\Api\V1\User\Models\User;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    protected User $user;
    protected ?array $profileData = null;

    public function __construct(User $user)
    {
        $this->user = $user;

        if ($this->isFullAdmin()) {
            $this->profileData = null;
        } else {
            $this->loadProfileData();
        }
    }

    protected function isFullAdmin(): bool
    {
        if ((int) ($this->user->is_admin ?? 0) === 1) {
            return true;
        }

        $role = strtolower((string) ($this->user->role ?? ''));

        return in_array($role, ['admin', 'superadmin', 'owner'], true);
    }

    protected function loadProfileData(): void
    {
        $orgId = $this->user->organization_id;

        $roleIds = DB::table('roles')
            ->join('role_user_rel', 'roles.id', '=', 'role_user_rel.role_id')
            ->where('role_user_rel.user_id', $this->user->id)
            ->where('role_user_rel.organization_id', $orgId)
            ->where('roles.deleted', 0)
            ->where(function ($q) {
                $q->where('roles.status', 1)
                    ->orWhere('roles.status', '1')
                    ->orWhere('roles.status', 'Active');
            })
            ->pluck('roles.id')
            ->unique()
            ->filter();

        if ($roleIds->isEmpty()) {
            $this->profileData = null;
            return;
        }

        $profileIds = DB::table('role_profile_rel')
            ->whereIn('role_id', $roleIds)
            ->where('organization_id', $orgId)
            ->pluck('profile_id')
            ->unique()
            ->filter();

        if ($profileIds->isEmpty()) {
            $this->profileData = null;
            return;
        }

        $merged = ['modules' => []];

        foreach ($profileIds as $profileId) {
            $profileFile = base_path("Profiles/{$orgId}/{$profileId}_Profile.php");
            if (! file_exists($profileFile)) {
                continue;
            }
            $data = include $profileFile;
            if (! is_array($data) || ! isset($data['modules']) || ! is_array($data['modules'])) {
                continue;
            }
            $this->mergeProfileData($merged, $data);
        }

        if (empty($merged['modules'])) {
            $this->profileData = null;
            return;
        }

        $this->profileData = $merged;
    }

    protected function mergeProfileData(array &$merged, array $incoming): void
    {
        foreach ($incoming['modules'] as $module => $modData) {
            if (! isset($merged['modules'][$module])) {
                $merged['modules'][$module] = ['permissions' => [], 'fields' => []];
            }

            foreach ($modData['permissions'] ?? [] as $actionKey => $val) {
                $prev = $merged['modules'][$module]['permissions'][$actionKey] ?? 0;
                $merged['modules'][$module]['permissions'][$actionKey] = max((int) $prev, (int) $val);
            }

            foreach ($modData['fields'] ?? [] as $fieldId => $settings) {
                if (! isset($merged['modules'][$module]['fields'][$fieldId])) {
                    $merged['modules'][$module]['fields'][$fieldId] = $settings;
                    continue;
                }
                $prev = $merged['modules'][$module]['fields'][$fieldId];
                $merged['modules'][$module]['fields'][$fieldId] = [
                    'invisible' => min((int) ($prev['invisible'] ?? 1), (int) ($settings['invisible'] ?? 1)),
                    'editable' => max((int) ($prev['editable'] ?? 0), (int) ($settings['editable'] ?? 0)),
                    'readonly' => min((int) ($prev['readonly'] ?? 1), (int) ($settings['readonly'] ?? 1)),
                ];
            }
        }
    }

    /**
     * Bakery role fallback when CRM profile files are not configured.
     */
    protected function hasBakeryRoleAccess(string $module): bool
    {
        if (! method_exists($this->user, 'getAllowedModules')) {
            return false;
        }

        $allowed = collect($this->user->getAllowedModules())
            ->pluck('value')
            ->map(fn ($v) => strtolower((string) $v));

        return $allowed->contains(strtolower($module));
    }

    public function hasPermission(string $module, string $actionKey = 'view'): bool
    {
        if ($this->isFullAdmin()) {
            return true;
        }

        if ($this->profileData) {
            if (! isset($this->profileData['modules'][$module]['permissions'][$actionKey])) {
                // Fall through to bakery role if profile lacks this module
                return $this->hasBakeryRoleAccess($module);
            }

            return $this->profileData['modules'][$module]['permissions'][$actionKey] === 1;
        }

        return $this->hasBakeryRoleAccess($module);
    }

    public function canViewField(string $module, string $fieldId): bool
    {
        if ($this->isFullAdmin()) {
            return true;
        }

        if ($fieldId === 'id') {
            return true;
        }

        if (!$this->profileData) {
            return $this->hasBakeryRoleAccess($module);
        }

        $fieldSettings = $this->profileData['modules'][$module]['fields'][$fieldId] ?? null;

        if (!$fieldSettings) {
            return $this->hasBakeryRoleAccess($module);
        }

        return (int) ($fieldSettings['invisible'] ?? 1) === 0;
    }

    public function canWriteField(string $module, string $fieldId): bool
    {
        if ($this->isFullAdmin()) {
            return true;
        }

        if ($fieldId === 'id') {
            return true;
        }

        if (!$this->profileData) {
            return $this->hasBakeryRoleAccess($module);
        }

        $fieldSettings = $this->profileData['modules'][$module]['fields'][$fieldId] ?? null;

        if (!$fieldSettings) {
            return $this->hasBakeryRoleAccess($module);
        }

        if ((int) ($fieldSettings['invisible'] ?? 1) === 1) {
            return false;
        }

        return (int) ($fieldSettings['editable'] ?? 0) === 1;
    }
}
