<?php

namespace App\Console\Commands;

use App\Models\CrmField;
use App\Models\PicklistValue;
use App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MigrateModuleFieldsCommand extends Command
{
    protected $signature = 'migrate:module-fields {module?}';
    protected $description = 'Sync ModuleFieldConfig (displaytype, mandatory, labels) into crm_fields';

    public function handle()
    {
        $this->info('Migrating module fields...');

        $aliases = ModuleFieldConfig::getModuleAliases();
        $only = $this->argument('module');

        foreach ($aliases as $moduleName => $configKey) {
            if ($only && strcasecmp($only, $moduleName) !== 0 && strcasecmp($only, $configKey) !== 0) {
                continue;
            }

            $fields = ModuleFieldConfig::getFields($configKey);
            if (!$fields) {
                continue;
            }

            $this->info("Processing {$moduleName}...");
            $seq = 1;
            $tableName = Str::snake(Str::pluralStudly($moduleName));

            foreach ($fields as $fieldDef) {
                $apiFieldName = $fieldDef['fieldname'];
                $dbFieldName = Str::snake($apiFieldName);
                $mandatory = (int) ($fieldDef['mandatory'] ?? 0);
                $displaytype = (int) ($fieldDef['displaytype'] ?? 1);

                $crmField = CrmField::where('modulename', $moduleName)
                    ->where(function ($q) use ($dbFieldName, $apiFieldName) {
                        $q->where('fieldname', $dbFieldName)
                            ->orWhere('apifieldname', $apiFieldName);
                    })
                    ->orderBy('deleted') // prefer active row
                    ->first();

                $payload = [
                    'modulename' => $moduleName,
                    'fieldname' => $dbFieldName,
                    'fieldlabel' => $fieldDef['fieldlabel'],
                    'fieldtype' => $fieldDef['fieldtype'],
                    'tablename' => $tableName,
                    'mandatory' => $mandatory,
                    'apifieldname' => $apiFieldName,
                    'displaytype' => $displaytype,
                    'is_custom_field' => 0,
                    'seq' => $seq++,
                    'deleted' => 0,
                    'organization_id' => 'default',
                ];

                if (!$crmField) {
                    $crmField = CrmField::create(array_merge($payload, [
                        'id' => (string) Str::uuid(),
                    ]));
                } else {
                    $crmField->update($payload);
                }

                // Sync picklist options. Empty options[] clears stale hardcoded values (e.g. User.role).
                if (array_key_exists('options', $fieldDef) && is_array($fieldDef['options'])) {
                    if (count($fieldDef['options']) === 0) {
                        PicklistValue::where('field_id', $crmField->id)->delete();
                    } else {
                        $keepValues = [];
                        $sortOrder = 1;
                        foreach ($fieldDef['options'] as $option) {
                            $keepValues[] = (string) $option['value'];
                            $picklistValue = PicklistValue::where('field_id', $crmField->id)
                                ->where('value', $option['value'])
                                ->first();

                            if (!$picklistValue) {
                                PicklistValue::create([
                                    'id' => (string) Str::uuid(),
                                    'field_id' => $crmField->id,
                                    'value' => $option['value'],
                                    'label' => $option['label'],
                                    'sort_order' => $sortOrder++,
                                    'status' => 1,
                                ]);
                            } else {
                                $picklistValue->update([
                                    'label' => $option['label'],
                                    'sort_order' => $sortOrder++,
                                    'status' => 1,
                                ]);
                            }
                        }

                        PicklistValue::where('field_id', $crmField->id)
                            ->whereNotIn('value', $keepValues)
                            ->delete();
                    }
                } elseif (strtolower((string) ($fieldDef['fieldtype'] ?? '')) !== 'picklist'
                    && strtolower((string) ($fieldDef['fieldtype'] ?? '')) !== 'multiselect') {
                    // Non-picklist fields should not keep leftover picklist rows.
                    PicklistValue::where('field_id', $crmField->id)->delete();
                }

                // Soft-delete legacy User.is_active crm field if we standardize on status checkbox.
                if ($moduleName === 'User' && $apiFieldName === 'status') {
                    CrmField::where('modulename', 'User')
                        ->where(function ($q) {
                            $q->where('apifieldname', 'is_active')->orWhere('fieldname', 'is_active');
                        })
                        ->where('apifieldname', '!=', 'status')
                        ->update(['deleted' => 1, 'displaytype' => 2]);

                    PicklistValue::where('field_id', $crmField->id)->delete();
                }

                // Seed module_relation_fields for relation picklists (needed for FE module + search).
                $relatedModule = $fieldDef['related_module']
                    ?? $this->inferRelatedModule($apiFieldName, (string) ($fieldDef['fieldtype'] ?? ''));

                if ($relatedModule) {
                    $existingRel = \App\Models\ModuleRelationFields::where('modulename', $moduleName)
                        ->where('field_id', $crmField->id)
                        ->where('deleted', 0)
                        ->first();

                    if (! $existingRel) {
                        \App\Models\ModuleRelationFields::create([
                            'id' => (string) Str::uuid(),
                            'field_id' => $crmField->id,
                            'modulename' => $moduleName,
                            'related_module' => $relatedModule,
                            'deleted' => 0,
                        ]);
                    } else {
                        $existingRel->update(['related_module' => $relatedModule]);
                    }
                }
            }
        }

        $this->info('Migration complete!');

        return self::SUCCESS;
    }

    private function inferRelatedModule(string $apiField, string $fieldtype): ?string
    {
        if (! in_array(strtolower($fieldtype), ['relationpicklist', 'multirelationpicklist'], true)) {
            return null;
        }

        $map = [
            'branchId' => 'Branch',
            'organizationId' => 'Organization',
            'vendorId' => 'Vendor',
            'ingredientId' => 'Ingredient',
            'productId' => 'Product',
            'userId' => 'User',
            'billingId' => 'Billing',
            'recipeId' => 'Recipe',
        ];

        if (isset($map[$apiField])) {
            return $map[$apiField];
        }

        if (Str::endsWith($apiField, 'Id')) {
            return Str::studly(Str::beforeLast($apiField, 'Id'));
        }

        return null;
    }
}
