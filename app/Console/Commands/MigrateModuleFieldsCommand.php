<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use App\Models\CrmField;
use App\Models\PicklistValue;
use App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig;

class MigrateModuleFieldsCommand extends Command
{
    protected $signature = 'migrate:module-fields';
    protected $description = 'Migrate standard fields from ModuleFieldConfig to DB';

    public function handle()
    {
        $this->info('Migrating module fields...');

        $aliases = [
            'Organization' => 'organizations',
            'User' => 'users',
            'Vendor' => 'vendors',
            'Ingredient' => 'ingredients',
            'InventoryTransaction' => 'inventory_transactions',
            'Product' => 'products',
            'Recipe' => 'recipes',
            'Branch' => 'branches',
            'ProductionBatch' => 'production_batches',
            'BranchStock' => 'branch_stocks',
            'BranchTransfer' => 'branch_transfers',
            'BranchDailyReport' => 'branch_daily_reports',
            'Billing' => 'billings',
            'BillingItem' => 'billing_items',
        ];

        foreach ($aliases as $moduleName => $configKey) {
            $fields = ModuleFieldConfig::getFields($configKey);
            if (!$fields) continue;

            $this->info("Processing {$moduleName}...");
            $seq = 1;
            
            foreach ($fields as $fieldDef) {
                $apiFieldName = $fieldDef['fieldname'];
                $dbFieldName = Str::snake($apiFieldName);
                
                $crmField = CrmField::where('modulename', $moduleName)
                                    ->where('fieldname', $dbFieldName)
                                    ->first();

                if (!$crmField) {
                    $crmField = CrmField::create([
                        'id' => (string) Str::uuid(),
                        'modulename' => $moduleName,
                        'fieldname' => $dbFieldName,
                        'fieldlabel' => $fieldDef['fieldlabel'],
                        'fieldtype' => $fieldDef['fieldtype'],
                        'tablename' => Str::snake(Str::plural($moduleName)),
                        'mandatory' => in_array($dbFieldName, ['id', 'name']) ? 1 : 0, 
                        'apifieldname' => $apiFieldName,
                        'displaytype' => $fieldDef['displaytype'] ?? 1,
                        'is_custom_field' => 0,
                        'seq' => $seq++,
                    ]);
                } else {
                    $crmField->update([
                        'fieldlabel' => $fieldDef['fieldlabel'],
                        'fieldtype' => $fieldDef['fieldtype'],
                        'apifieldname' => $apiFieldName,
                        'displaytype' => $fieldDef['displaytype'] ?? 1,
                    ]);
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
                                'status' => 1
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
    }
}
