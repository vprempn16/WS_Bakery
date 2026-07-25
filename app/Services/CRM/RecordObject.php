<?php

namespace App\Services\CRM;

use App\Exceptions\PermissionDeniedException;
use App\Models\BKModel;
use App\Models\FieldModelManager;
use App\Modules\Api\V1\Profile\Services\ModuleService;
use App\Modules\Api\V1\User\Models\User;
use App\Services\AuthUser;
use App\Services\PermissionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class RecordObject
{
    public static function make(
        string $module,
        ?string $id = null,
        array $data = [],
        string $viewType = 'DetailView'
    ): BKModel {
        if ($id === 'new') {
            $id = null;
        }

        $resolvedModule = ModuleService::resolveName($module);

        $class = "\\App\\Modules\\Api\\V1\\{$resolvedModule}\\Models\\{$resolvedModule}";
        $modelClass = class_exists($class) ? $class : BKModel::class;

        $exempt = [
            'Organization',
            'User',
            'GlobalSearchIndex',
            'ModuleNumberingDetail',
            'AuditLog',
            'ModuleRelationFields',
        ];

        if ($id) {
            try {
                $model = $modelClass::findOrFail($id);
                if (method_exists($model, 'loadCustomValues')) {
                    $model->loadCustomValues();
                }
            } catch (ModelNotFoundException $e) {
                $other = $modelClass::withoutGlobalScopes()->find($id);
                if ($other && isset($other->deleted) && (int) $other->deleted === 1) {
                    throw $e;
                }
                $user = AuthUser::user();
                if (
                    $other &&
                    !in_array($resolvedModule, $exempt, true) &&
                    isset($other->organization_id) &&
                    $user &&
                    $other->organization_id !== $user->organization_id
                ) {
                    throw new \Exception('This is not your organization’s record.');
                }
                throw $e;
            }
        } else {
            $model = new $modelClass();
        }

        if (method_exists($model, 'setViewType')) {
            $model->setViewType($viewType);
        } else {
            $model->_viewType = $viewType;
        }

        $user = AuthUser::user();
        if (!$user instanceof User) {
            throw new PermissionDeniedException('Authentication required.');
        }

        $permissionService = new PermissionService($user);
        $isAdmin = (int) ($user->is_admin ?? 0) === 1
            || in_array(strtolower((string) ($user->role ?? '')), ['admin', 'superadmin', 'owner'], true);

        $action = match ($viewType) {
            'CreateView' => 'create',
            'EditView' => 'edit',
            default => 'view',
        };

        if (!$permissionService->hasPermission($resolvedModule, $action)) {
            throw new PermissionDeniedException(
                "You don't have permission to {$action} {$resolvedModule}"
            );
        }

        if (!empty($data)) {
            if (!method_exists($model, 'fill')) {
                throw new \RuntimeException("Model [{$modelClass}] does not support fill().");
            }

            if ($isAdmin) {
                $model->fill($data);
            } else {
                $clean = [];
                $organizationId = $user->organization_id ?? null;

                foreach ($data as $apiField => $value) {
                    $fieldId = FieldModelManager::getFieldId($resolvedModule, $apiField, $organizationId);

                    if (!$fieldId) {
                        // Unknown to crm_fields — still allow bakery API payloads through fill
                        $clean[$apiField] = $value;
                        continue;
                    }

                    if (!$permissionService->canWriteField($resolvedModule, $fieldId)) {
                        throw new PermissionDeniedException(
                            "You don't have permission to edit this field"
                        );
                    }

                    $clean[$apiField] = $value;
                }

                $model->fill($clean);
            }
        }

        return $model;
    }

    public static function saveWithRelations(
        BKModel $model,
        array $relatedRecords = []
    ): BKModel {
        return DB::transaction(function () use ($model, $relatedRecords) {
            $model->save();
            RelationshipService::processRelationships($model, $relatedRecords);

            return $model;
        });
    }
}
