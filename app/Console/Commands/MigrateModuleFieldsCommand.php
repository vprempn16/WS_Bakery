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

                if (isset($fieldDef['options']) && is_array($fieldDef['options'])) {
                    $sortOrder = 1;
                    foreach ($fieldDef['options'] as $option) {
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
                            ]);
                        }
                    }
                }
            }
        }

        $this->info('Migration complete!');

        return self::SUCCESS;
    }
}
