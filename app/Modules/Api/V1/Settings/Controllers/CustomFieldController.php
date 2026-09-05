<?php

namespace App\Modules\Api\V1\Settings\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;

use App\Http\Controllers\Controller;
use App\Models\CrmField;
use App\Models\FieldModelManager;
use App\Models\ModuleRelationFields;
use App\Modules\Api\V1\Profile\Services\ModuleService;

class CustomFieldController extends Controller
{
    public function createViewFields(Request $request)
    {
        $fieldTypes = [
            ['value' => 'text', 'label' => 'Text'],
            ['value' => 'textarea', 'label' => 'Textarea'],
            ['value' => 'number', 'label' => 'Number'],
            ['value' => 'email', 'label' => 'Email'],
            ['value' => 'date', 'label' => 'Date'],
            ['value' => 'datetime', 'label' => 'Datetime'],
            ['value' => 'picklist', 'label' => 'Picklist'],
            ['value' => 'multiselect', 'label' => 'Multi Select'],
            ['value' => 'relationPickList', 'label' => 'Relation Picklist'],
            ['value' => 'multiRelationPicklist', 'label' => 'Multi Relation Picklist'],
            ['value' => 'checkbox', 'label' => 'Checkbox'],
        ];

        $moduleOptions = ModuleService::getEntityPortalModules()
            ->map(fn ($m) => [
                'value' => $m->modulename,
                'label' => $m->modulelabel ?: $m->modulename,
            ])
            ->values()
            ->all();

        $fields = [
            ['name' => 'modulename', 'label' => 'Module', 'type' => 'text', 'required' => true],
            ['name' => 'fieldlabel', 'label' => 'Field Label', 'type' => 'text', 'required' => true],
            [
                'name' => 'fieldtype',
                'label' => 'Field Type',
                'type' => 'picklist',
                'required' => true,
                'options' => $fieldTypes,
            ],
            ['name' => 'mandatory', 'label' => 'Mandatory', 'type' => 'checkbox'],
            [
                'name' => 'related_modules',
                'label' => 'Related Module',
                'type' => 'picklist',
                'required' => true,
                'options' => $moduleOptions,
                'showIf' => ['fieldtype' => ['relationPickList', 'multiRelationPicklist']],
            ],
            [
                'name' => 'options',
                'label' => 'Options',
                'type' => 'array',
                'required' => true,
                'showIf' => ['fieldtype' => ['picklist', 'multiselect']],
            ],
        ];

        return $this->success([
            'fields' => $fields,
        ]);
    }

    public function list(Request $request)
    {
        $module = $request->query('module');
        if (!$module) {
            return $this->error('Module is required', null, null, null, 400);
        }

        $module = preg_replace('/[^a-zA-Z]/', '', $module);

        // ProfileView includes displaytypes 1/2/3 so settings can list all fields per module.
        $fields = FieldModelManager::make($module, 'ProfileView', false)->getApiFormFields();

        return $this->success($fields);
    }

    public function create(Request $request)
    {
        DB::beginTransaction();

        try {
            $data = $request->input('data', []);

            $data = validator($data, [
                'id' => 'nullable|string',
                'fieldlabel' => 'required|string|max:150',
                'fieldtype' => 'required|string',
                'modulename' => 'required|string',
                'mandatory' => 'nullable|in:0,1',
                'options' => 'array',
                'options.*.label' => 'required|string|max:100',
                'options.*.value' => 'nullable|string|max:100',
                'related_modules' => 'nullable|array',
                'related_modules.*' => 'string|max:100',
            ])->validate();

            $allowedTypes = [
                'text', 'textarea', 'number', 'email',
                'date', 'datetime', 'picklist',
                'multiselect', 'checkbox',
                'relationPickList', 'multiRelationPicklist',
            ];

            if (!in_array($data['fieldtype'], $allowedTypes, true)) {
                DB::rollBack();
                return $this->error('Invalid field type', null, null, null, 400);
            }

            $isRelation = in_array($data['fieldtype'], ['relationPickList', 'multiRelationPicklist'], true);
            $isPicklist = in_array($data['fieldtype'], ['picklist', 'multiselect'], true);

            if ($isRelation) {
                $related = array_values(array_filter($data['related_modules'] ?? []));
                if (empty($related)) {
                    DB::rollBack();
                    return $this->error('Related module is required for relation fields', null, null, null, 400);
                }
            }

            if ($isPicklist && empty($data['options'])) {
                DB::rollBack();
                return $this->error('At least one option is required for picklist / multi select', null, null, null, 400);
            }

            $organizationId = auth()->user()->organization_id ?? null;

            $module = preg_replace('/[^a-zA-Z0-9]/', '', $data['modulename']);

            if (empty($module) || strlen($module) > 50) {
                DB::rollBack();
                return $this->error('Invalid module name. Must be alphanumeric and max 50 characters.', null, null, null, 400);
            }

            $module = Str::snake($module);
            $customTable = "l{$module}_custom_values";

            DB::commit(); // End current transaction for DDL

            if (!Schema::hasTable($customTable)) {
                Schema::create($customTable, function (Blueprint $table) {
                    $table->char('id', 36)->primary();
                    $table->char('record_id', 36);
                    $table->uuid('organization_id')->nullable();
                    $table->char('field_id', 36);
                    $table->text('field_value')->nullable();
                    $table->timestamps();
                    $table->unique(['record_id', 'field_id'], "{$table->getTable()}_unique_record_field");
                });
            }

            DB::beginTransaction();

            $fieldId = (string) Str::uuid();
            $fieldname = Str::slug($data['fieldlabel'], '_');

            $exists = CrmField::where('modulename', $data['modulename'])
                ->where('fieldname', $fieldname)
                ->where('deleted', 0)
                ->exists();

            if ($exists) {
                DB::rollBack();
                return $this->error(
                    "Field '{$data['fieldlabel']}' already exists in module '{$data['modulename']}'.",
                    null,
                    null,
                    null,
                    400
                );
            }

            $seq = CrmField::where('modulename', $data['modulename'])
                ->where('deleted', 0)
                ->max('seq') ?? 0;

            $field = CrmField::create([
                'id' => $fieldId,
                'modulename' => $data['modulename'],
                'fieldname' => $fieldname,
                'fieldlabel' => $data['fieldlabel'],
                'fieldtype' => $data['fieldtype'],
                'tablename' => $customTable,
                'mandatory' => $data['mandatory'] ?? 0,
                'apifieldname' => Str::camel($fieldname),
                'displaytype' => 1,
                'is_custom_field' => 1,
                'organization_id' => $organizationId,
                'seq' => $seq + 1,
            ]);

            if ($isPicklist) {
                foreach ($data['options'] ?? [] as $i => $opt) {
                    DB::table('picklist_values')->insert([
                        'id' => (string) Str::uuid(),
                        'field_id' => $fieldId,
                        'label' => $opt['label'],
                        'value' => $opt['value'] ?? Str::slug($opt['label'], '_'),
                        'sort_order' => $i + 1,
                        'status' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if ($isRelation) {
                foreach (array_values(array_unique(array_filter($data['related_modules'] ?? []))) as $relatedModule) {
                    ModuleRelationFields::create([
                        'id' => (string) Str::uuid(),
                        'field_id' => $fieldId,
                        'modulename' => $data['modulename'],
                        'related_module' => $relatedModule,
                        'deleted' => 0,
                    ]);
                }
            }

            DB::commit();

            return $this->success($field, 'Custom field created successfully');
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('CUSTOM_FIELD_ERROR', ['error' => $e->getMessage()]);

            return $this->errorFromException($e, 'Failed to create custom field');
        }
    }

    public function show($module, $id)
    {
        $organizationId = auth()->user()->organization_id ?? null;
        $module = preg_replace('/[^a-zA-Z]/', '', $module);

        $field = CrmField::where('id', $id)
            ->where('modulename', $module)
            ->where('deleted', 0)
            ->first();

        if (!$field) {
            return $this->error('Field not found', null, null, null, 404);
        }

        if ((int) $field->is_custom_field === 0) {
            $override = DB::table('crm_default_field_definitions')
                ->where('organization_id', $organizationId)
                ->where('modulename', $field->modulename)
                ->where('fieldname', $field->fieldname)
                ->first();

            if ($override) {
                $field->fieldlabel = $override->fieldlabel;
                $field->mandatory = (int) $override->mandatory;
            }
        }

        $options = [];
        if (in_array($field->fieldtype, ['picklist', 'multiselect'], true)) {
            $options = DB::table('picklist_values')
                ->where('field_id', $field->id)
                ->where('status', 1)
                ->orderBy('sort_order')
                ->get(['label', 'value'])
                ->toArray();
        }

        $relatedModules = [];
        if (in_array($field->fieldtype, ['relationPickList', 'multiRelationPicklist'], true)) {
            $relatedModules = ModuleRelationFields::where('field_id', $field->id)
                ->where('deleted', 0)
                ->pluck('related_module')
                ->filter()
                ->values()
                ->all();
        }

        return $this->success([
            'id' => $field->id,
            'fieldlabel' => $field->fieldlabel,
            'fieldtype' => $field->fieldtype,
            'modulename' => $field->modulename,
            'mandatory' => (int) $field->mandatory,
            'is_custom_field' => (bool) (int) $field->is_custom_field,
            'options' => $options,
            'related_modules' => $relatedModules,
        ]);
    }

    public function updateFieldLabel(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->input('data', []);

            if (empty($data['id'])) {
                DB::rollBack();
                return $this->error('Field ID is required', null, null, null, 400);
            }

            $organizationId = auth()->user()->organization_id ?? null;

            $field = CrmField::where('id', $data['id'])
                ->where('deleted', 0)
                ->first();

            if (!$field) {
                DB::rollBack();
                return $this->error('Field not found', null, null, null, 404);
            }

            $isCustom = (int) $field->is_custom_field === 1;
            $mandatoryOnly = !empty($data['mandatory_only']) || !$isCustom;

            // System fields: only mandatory may change. Custom: label + mandatory.
            if ($isCustom && !$mandatoryOnly && empty($data['fieldlabel'])) {
                DB::rollBack();
                return $this->error('Field label is required', null, null, null, 400);
            }

            if ($isCustom) {
                $update = [
                    'mandatory' => $data['mandatory'] ?? $field->mandatory,
                    'updated_at' => now(),
                ];
                if (!$mandatoryOnly && !empty($data['fieldlabel'])) {
                    $update['fieldlabel'] = $data['fieldlabel'];
                }
                $field->update($update);
            } else {
                // Keep the system label (or existing override label); only persist mandatory.
                $existingOverride = DB::table('crm_default_field_definitions')
                    ->where('organization_id', $organizationId)
                    ->where('modulename', $field->modulename)
                    ->where('fieldname', $field->fieldname)
                    ->first();

                $label = $existingOverride->fieldlabel ?? $field->fieldlabel;

                DB::table('crm_default_field_definitions')->updateOrInsert(
                    [
                        'organization_id' => $organizationId,
                        'modulename' => $field->modulename,
                        'fieldname' => $field->fieldname,
                    ],
                    [
                        'fieldlabel' => $label,
                        'mandatory' => $data['mandatory'] ?? $field->mandatory,
                        'seq' => $field->seq,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            DB::commit();

            return $this->success([
                'message' => 'Field label updated successfully',
                'field_id' => $field->id,
            ], 'Field label updated successfully');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CUSTOM_FIELD_UPDATE_LABEL_ERROR', ['error' => $e->getMessage()]);

            return $this->errorFromException($e, 'Failed to update field label');
        }
    }

    public function delete($id)
    {
        DB::beginTransaction();

        try {
            $organizationId = auth()->user()->organization_id ?? null;

            $field = CrmField::where('id', $id)
                ->where('deleted', 0)
                ->where('is_custom_field', 1)
                ->first();

            if (!$field) {
                DB::rollBack();
                return $this->error('Custom field not found or cannot be deleted', null, null, null, 404);
            }

            if (in_array($field->fieldtype, ['picklist', 'multiselect'], true)) {
                DB::table('picklist_values')
                    ->where('field_id', $field->id)
                    ->update([
                        'status' => 0,
                        'updated_at' => now(),
                    ]);
            }

            if ($field->tablename && Schema::hasTable($field->tablename)) {
                $query = DB::table($field->tablename)->where('field_id', $field->id);
                if (Schema::hasColumn($field->tablename, 'organization_id') && $organizationId) {
                    $query->where('organization_id', $organizationId);
                }
                $query->delete();
            }

            $field->update([
                'deleted' => 1,
                'updated_at' => now(),
            ]);

            DB::commit();

            return $this->success([
                'message' => 'Custom field deleted successfully',
                'field_id' => $field->id,
            ], 'Custom field deleted successfully');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CUSTOM_FIELD_DELETE_ERROR', [
                'error' => $e->getMessage(),
                'field_id' => $id,
            ]);

            return $this->errorFromException($e, 'Failed to delete custom field');
        }
    }
}
