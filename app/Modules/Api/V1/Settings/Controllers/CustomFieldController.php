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

class CustomFieldController extends Controller
{
    public function list(Request $request)
    {
        $module = $request->query('module');
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module is required'], 400);
        }

        $module = preg_replace('/[^a-zA-Z]/', '', $module);

        $fields = FieldModelManager::make($module, 'DetailView', false)->getApiFormFields();

        return response()->json(['success' => true, 'data' => $fields]);
    }

    public function create(Request $request)
    {
        DB::beginTransaction();

        try {
            $data = $request->input('data', []);

            $data = validator($data, [
                'id'                    => 'nullable|string',
                'fieldlabel'            => 'required|string|max:150',
                'fieldtype'             => 'required|string',
                'modulename'            => 'required|string',
                'mandatory'             => 'nullable|in:0,1',
                'options'               => 'array',
                'options.*.label'       => 'required|string|max:100',
                'options.*.value'       => 'nullable|string|max:100',
            ])->validate();

            $allowedTypes = [
                'text', 'textarea', 'number', 'email',
                'date', 'datetime', 'picklist',
                'multiselect', 'checkbox',
            ];

            if (!in_array($data['fieldtype'], $allowedTypes, true)) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Invalid field type'], 400);
            }

            $organizationId = auth()->user()->organization_id ?? null;

            $module = preg_replace('/[^a-zA-Z0-9]/', '', $data['modulename']);

            if (empty($module) || strlen($module) > 50) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Invalid module name. Must be alphanumeric and max 50 characters.'], 400);
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

            $fieldId   = (string) Str::uuid();
            $fieldname = Str::slug($data['fieldlabel'], '_');

            $exists = CrmField::where('modulename', $data['modulename'])
                ->where('fieldname', $fieldname)
                ->where('deleted', 0)
                ->exists();

            if ($exists) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => "Field '{$data['fieldlabel']}' already exists in module '{$data['modulename']}'."], 400);
            }

            $seq = CrmField::where('modulename', $data['modulename'])
                ->where('deleted', 0)
                ->max('seq') ?? 0;

            $field = CrmField::create([
                'id'              => $fieldId,
                'modulename'      => $data['modulename'],
                'fieldname'       => $fieldname,
                'fieldlabel'      => $data['fieldlabel'],
                'fieldtype'       => $data['fieldtype'],
                'tablename'       => $customTable,
                'mandatory'       => $data['mandatory'] ?? 0,
                'apifieldname'    => Str::camel($fieldname),
                'displaytype'     => 1,
                'is_custom_field' => 1,
                'organization_id' => $organizationId,
                'seq'             => $seq + 1,
            ]);

            if (in_array($data['fieldtype'], ['picklist', 'multiselect'], true)) {
                foreach ($data['options'] ?? [] as $i => $opt) {
                    DB::table('picklist_values')->insert([
                        'id'         => (string) Str::uuid(),
                        'field_id'   => $fieldId,
                        'label'      => $opt['label'],
                        'value'      => Str::slug($opt['label'], '_'),
                        'sort_order' => $i + 1,
                        'status'     => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::commit();

            return response()->json(['success' => true, 'data' => $field]);

        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('CUSTOM_FIELD_ERROR', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to create custom field', 'error' => $e->getMessage()], 500);
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
            return response()->json(['success' => false, 'message' => 'Field not found'], 404);
        }

        if ((int) $field->is_custom_field === 0) {
            $override = DB::table('crm_default_field_definitions')
                ->where('organization_id', $organizationId)
                ->where('modulename', $field->modulename)
                ->where('fieldname', $field->fieldname)
                ->first();

            if ($override) {
                $field->fieldlabel = $override->fieldlabel;
                $field->mandatory  = (int) $override->mandatory;
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

        return response()->json([
            'success' => true,
            'data' => [
                'id'              => $field->id,
                'fieldlabel'      => $field->fieldlabel,
                'fieldtype'       => $field->fieldtype,
                'modulename'      => $field->modulename,
                'mandatory'       => (int) $field->mandatory,
                'options'         => $options,
            ]
        ]);
    }

    public function updateFieldLabel(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->input('data', []);

            if (empty($data['id'])) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Field ID is required'], 400);
            }

            if (empty($data['fieldlabel'])) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Field label is required'], 400);
            }

            $organizationId = auth()->user()->organization_id ?? null;

            $field = CrmField::where('id', $data['id'])
                ->where('deleted', 0)
                ->first();

            if (!$field) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Field not found'], 404);
            }

            if ((int) $field->is_custom_field === 1) {
                $field->update([
                    'fieldlabel' => $data['fieldlabel'],
                    'mandatory'  => $data['mandatory'] ?? $field->mandatory,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('crm_default_field_definitions')->updateOrInsert(
                    [
                        'organization_id' => $organizationId,
                        'modulename'      => $field->modulename,
                        'fieldname'       => $field->fieldname,
                    ],
                    [
                        'fieldlabel' => $data['fieldlabel'],
                        'mandatory'  => $data['mandatory'] ?? $field->mandatory,
                        'seq'        => $field->seq,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'message'  => 'Field label updated successfully',
                    'field_id' => $field->id,
                ]
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CUSTOM_FIELD_UPDATE_LABEL_ERROR', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to update field label'], 500);
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
                return response()->json(['success' => false, 'message' => 'Custom field not found or cannot be deleted'], 404);
            }

            if (in_array($field->fieldtype, ['picklist', 'multiselect'], true)) {
                DB::table('picklist_values')
                    ->where('field_id', $field->id)
                    ->update([
                        'status'     => 0,
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
                'deleted'    => 1,
                'updated_at'=> now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'message'  => 'Custom field deleted successfully',
                    'field_id' => $field->id,
                ]
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CUSTOM_FIELD_DELETE_ERROR', [
                'error' => $e->getMessage(),
                'field_id' => $id,
            ]);

            return response()->json(['success' => false, 'message' => 'Failed to delete custom field'], 500);
        }
    }
}
