<?php

namespace App\Modules\Api\V1\Profile\Services;

use App\Models\FieldModelManager;
use App\Modules\Api\V1\Profile\Models\ProfileModuleAction;
use App\Modules\Api\V1\Profile\Models\ProfileModuleField;
use App\Modules\Api\V1\Profile\Models\SystemAction;

class ProfileSaveService
{
    protected $profileId;
    protected $user;

    public function __construct($profileId, $user)
    {
        $this->profileId = $profileId;
        $this->user = $user;
    }

    /**
     * Persist unified permissions.
     * $permissions = [ 'Ingredient' => ['view'=>1,'create'=>1,'edit'=>0,'delete'=>0], ... ]
     * $fields = [ 'Ingredient' => [ 'name' => ['invisible'=>0,'readonly'=>0,'editable'=>1], ... ] ]
     */
    public function saveUnified(array $permissions = [], array $fields = []): void
    {
        $actionMap = SystemAction::pluck('id', 'action_key')->toArray();

        ProfileModuleAction::where('profileid', $this->profileId)->delete();

        $now = now();
        $actionRows = [];
        foreach ($permissions as $module => $actions) {
            foreach ($actions as $actionKey => $value) {
                if (!isset($actionMap[$actionKey])) {
                    continue;
                }
                // id is auto-increment bigint — do not insert UUID
                $actionRows[] = [
                    'profileid' => $this->profileId,
                    'organization_id' => $this->user->organization_id,
                    'modulename' => $module,
                    'action_id' => $actionMap[$actionKey],
                    'permission' => (int) $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        if (!empty($actionRows)) {
            ProfileModuleAction::insert($actionRows);
        }

        if (!empty($fields)) {
            ProfileModuleField::where('profileid', $this->profileId)->delete();

            $fieldRows = [];
            foreach ($fields as $module => $moduleFields) {
                foreach ($moduleFields as $fieldKey => $perms) {
                    $fieldId = $this->isUuid($fieldKey)
                        ? $fieldKey
                        : FieldModelManager::getFieldId($module, $fieldKey, $this->user->organization_id ?? null);
                    if (!$fieldId) {
                        continue;
                    }

                    $fieldRows[] = [
                        'profileid' => $this->profileId,
                        'organization_id' => $this->user->organization_id,
                        'modulename' => $module,
                        'field_id' => $fieldId,
                        'invisible' => (int) ($perms['invisible'] ?? 0),
                        'readonly' => (int) ($perms['readonly'] ?? 0),
                        'editable' => (int) ($perms['editable'] ?? 0),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            if (!empty($fieldRows)) {
                ProfileModuleField::insert($fieldRows);
            }
        }
    }

    protected function isUuid($v): bool
    {
        return (bool) preg_match('/^[0-9a-fA-F\\-]{36}$/', $v);
    }
}
