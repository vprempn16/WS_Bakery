<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BaseModel extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $customAttributes = [];
    protected $fieldModelManager;
    protected ?string $_viewType = null;

    protected function getModuleName(): string
    {
        return class_basename($this);
    }

    protected function getViewType(): string
    {
        return $this->_viewType ?? 'DetailView';
    }

    protected function getFieldModelManager(): FieldModelManager
    {
        if (!$this->fieldModelManager) {
            $this->fieldModelManager = FieldModelManager::make($this->getModuleName(), $this->getViewType(), true);
        }
        return $this->fieldModelManager;
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function fill(array $attributes)
    {
        $fields = $this->getFieldModelManager()->getFields();
        $customFields = [];
        $standardFields = [];

        foreach ($attributes as $key => $value) {
            $field = $fields[$key] ?? null;
            if ($field) {
                $fieldName = $field->getFieldName();
                if (($value === null || $value === '') && !$field->isMandatory()) {
                    $type = strtolower($field->getFieldType());
                    if (in_array($type, ['relationpicklist', 'multirelationpicklist'], true)) {
                        if ($field->isCustomField()) {
                            $customFields[$fieldName] = null;
                        } else {
                            $this->setAttribute($fieldName, null);
                            $standardFields[$fieldName] = null;
                        }
                        continue;
                    }
                    continue;
                }
                if ($field->isCustomField()) {
                    $customFields[$fieldName] = $value;
                } else {
                    $this->setAttribute($fieldName, $value === '' ? '' : $value);
                    $standardFields[$fieldName] = $value === '' ? '' : $value;
                }
            }
        }
        parent::fill($standardFields);
        $this->customAttributes = array_merge($this->customAttributes ?? [], $customFields);
        return $this;
    }

    public function save(array $options = [])
    {
        if (empty($this->id)) {
            $this->id = (string) Str::uuid();
        }
        $this->validateBeforeSave();
        $saved = parent::save($options);
        if ($saved) {
            $this->saveCustomValues();
            $this->loadCustomValues();
        }
        return $saved;
    }

    protected function validateBeforeSave(): void
    {
        $fieldManager = $this->getFieldModelManager();
        $apiMap = $fieldManager->getFieldToApiMap(); 

        $data = [];
        $onlyFields = [];

        foreach ($this->getDirty() as $dbField => $value) {
            $apiField = $apiMap[$dbField] ?? $dbField;
            $data[$apiField] = $value;
            $onlyFields[] = $apiField;
        }

        foreach ($this->customAttributes as $dbField => $value) {
            $apiField = $apiMap[$dbField] ?? $dbField;
            $data[$apiField] = $value;
            $onlyFields[] = $apiField;
        }

        try {
            if ($this->exists) {
                $fieldManager->validatePartial($data, array_values(array_unique($onlyFields)));
            } else {
                $fieldManager->validate($data);
            }

            $customFieldNames = collect($fieldManager->getCustomFields())
                ->map(fn ($f) => $f->getFieldName())
                ->flip()
                ->toArray();

            foreach ($data as $apiField => $value) {
                $dbField = array_search($apiField, $apiMap, true);
                if (!$dbField) continue;

                $fieldModel = $fieldManager->getFieldModel($apiField);
                if ($fieldModel && ($value === null || $value === '') && !$fieldModel->isMandatory()) {
                    continue;
                }

                if (array_key_exists($dbField, $customFieldNames)) {
                    $this->customAttributes[$dbField] = $value;
                } else {
                    $this->setAttribute($dbField, $value);
                }
            }
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    public function saveCustomValues(): void
    {
        if (empty($this->customAttributes)) return;

        $module = $this->getModuleName();
        $customTable = 'l' . strtolower($module) . '_custom_values';
        $organization_id = auth()->user()->organization_id ?? null;

        if (!Schema::hasTable($customTable)) return;

        $customFields = $this->getFieldModelManager()->getCustomFields();
        $fieldMap = collect($customFields)->mapWithKeys(fn($f) => [$f->getFieldName() => $f->getId()])->toArray();
        $now = now();
        $customInsertData = [];

        foreach ($this->customAttributes as $field => $value) {
            if (!isset($fieldMap[$field])) continue;

            $customInsertData[] = [
                'id'              => (string) Str::uuid(),
                'record_id'       => $this->id,
                'organization_id' => $organization_id,
                'field_id'        => $fieldMap[$field],
                'field_value'     => $value,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        if (!empty($customInsertData)) {
            DB::table($customTable)->upsert(
                $customInsertData,
                ['record_id', 'field_id', 'organization_id'],
                ['field_value', 'updated_at']
            );
        }
    }

    public function loadCustomValues(): void
    {
        $module = $this->getModuleName();
        $customTable = 'l' . strtolower($module) . '_custom_values';

        if (!Schema::hasTable($customTable)) return;

        $fieldMap = $this->getCustomFieldMap();
        
        $query = DB::table($customTable)->where('record_id', $this->getKey());
        if (Schema::hasColumn($customTable, 'organization_id') && isset($this->organization_id)) {
            $query->where('organization_id', $this->organization_id);
        }

        $customRows = $query->get();
        foreach ($customRows as $row) {
            if (isset($fieldMap[$row->field_id])) {
                $this->customAttributes[$fieldMap[$row->field_id]] = $row->field_value;
            }
        }
    }

    protected function getCustomFieldMap(): array
    {
        $customFields = $this->getFieldModelManager()->getCustomFields();
        return collect($customFields)
            ->mapWithKeys(fn($f) => [$f->getId() => $f->getFieldName()])
            ->toArray();
    }

    public function getFields() {
        return $this->getFieldModelManager()->getFields();
    }

    public function getApiFormFields() {
        return $this->getFieldModelManager()->getApiFormFields();
    }

    public function transformToApiFormat(): array
    {
        if (empty($this->customAttributes)) {
            $this->loadCustomValues();
        }

        if (!$this->exists && empty($this->id)) return [];

        $fieldManager = $this->getFieldModelManager();
        $fields = $fieldManager->getFields();
        $output = [];

        foreach ($fieldManager->getApiFormFields() as $fieldArr) {
            $apiField = $fieldArr['fieldname'];
            $field = $fields[$apiField] ?? null;
            if ($field) {
                $dbField = $field->getFieldName();
                $value = $this->$dbField ?? null;
                $output[$apiField] = $value;
            }
        }

        if (!isset($output['id'])) {
            $output['id'] = $this->id;
        }

        $apiMap = $fieldManager->getFieldToApiMap(); 
        foreach ($this->customAttributes as $dbField => $value) {
            $apiKey = $apiMap[$dbField] ?? $dbField;
            $output[$apiKey] = $value;
        }

        return $output;
    }
}
