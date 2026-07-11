<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FieldModelManager
{
    protected string $module;
    protected array $fieldModels = [];
    protected string $viewType = 'DetailView';
    protected bool $profileValidation = false;

    public function __construct(string $module, string $viewType = 'DetailView', bool $profileValidation = false)
    {
        $this->module = $module;
        $this->viewType = $viewType;
        $this->profileValidation = $profileValidation;
    }

    public static function make(string $module, string $viewType = 'DetailView', bool $profileValidation = false): static
    {
        return (new static($module, $viewType, $profileValidation))->load();
    }

    public function load(): self
    {
        $organizationId = auth()->user()?->organization_id ?? null;

        if ($this->viewType == 'EditView' || $this->viewType == 'CreateView') {
            $displayTypes = [1];
        } else {
            $displayTypes = [1, 3];
        }

        $fields = CrmField::query()
            ->where('modulename', $this->module)
            ->whereIn('displaytype', $displayTypes)
            ->where('deleted', 0)
            ->where(function ($q) use ($organizationId) {
                $q->where('is_custom_field', 0)
                  ->orWhere(function ($q2) use ($organizationId) {
                      $q2->where('is_custom_field', 1)
                         ->where('organization_id', $organizationId);
                  });
            })
            ->orderBy('seq', 'asc')
            ->get();

        foreach ($fields as $field) {
            $model = new FieldModel($field);
            $this->fieldModels[$model->getAPIName()] = $model;
        }

        return $this;
    }

    public function getFields(): array
    {
        return $this->fieldModels;
    }

    public function getApiFormFields(): array
    {
        $fields = [];

        foreach ($this->fieldModels as $model) {
            $fieldArray = [
                'id'              => $model->getId(),
                'fieldname'       => $model->getAPIName(),
                'fieldlabel'      => $model->getLabel(),
                'mandatory'       => $model->isMandatory(),
                'fieldtype'       => $model->getFieldType(),
                'displaytype'     => $model->getDisplaytype(),
                'is_custom_field' => $model->isCustomField(),
            ];

            $typeLower = strtolower($model->getFieldType());

            if (in_array($typeLower, ['picklist', 'multiselect'])) {
                $fieldArray['options'] = $this->getPicklistOptions($model->getId());
            }

            if (in_array($typeLower, ['relationpicklist', 'multirelationpicklist'])) {
                $fieldArray['module'] = $this->getRelatedModuleForField($model);
            }

            $fields[] = $fieldArray;
        }

        return $fields;
    }

    public function getCustomFields(): array
    {
        return array_filter($this->fieldModels, fn ($f) => $f->isCustomField());
    }

    public function getFieldModel(string $apiName): ?FieldModel
    {
        return $this->fieldModels[$apiName] ?? null;
    }

    public function getFieldToApiMap(): array
    {
        return collect($this->fieldModels)
            ->mapWithKeys(fn ($f) => [$f->getFieldName() => $f->getAPIName()])
            ->toArray();
    }

    public function validate(array &$input): void
    {
        $errors = [];
        foreach ($this->getFields() as $fieldModel) {
            $apiField = $fieldModel->getAPIName();
            $value    = $input[$apiField] ?? null;
            try {
                $input[$apiField] = $fieldModel->validate($value);
            } catch (ValidationException $e) {
                $errors[$apiField] = $e->errors()[$apiField] ?? [$e->getMessage()];
            }
        }
        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function validatePartial(array &$input, array $onlyFields): void
    {
        $errors = [];
        $onlyLookup = array_flip($onlyFields);
        foreach ($this->getFields() as $fieldModel) {
            $apiField = $fieldModel->getAPIName();
            if (!isset($onlyLookup[$apiField])) continue;
            $value = $input[$apiField] ?? null;
            try {
                $input[$apiField] = $fieldModel->validate($value);
            } catch (ValidationException $e) {
                $errors[$apiField] = $e->errors()[$apiField] ?? [$e->getMessage()];
            }
        }
        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function getPicklistOptions(string $fid): array
    {
        return DB::table('picklist_values')
            ->where('field_id', $fid)
            ->where('status', 1)
            ->orderBy('sort_order')
            ->get(['value', 'label'])
            ->toArray();
    }

    private function getRelatedModuleForField(FieldModel $model): ?string
    {
        $relation = ModuleRelationFields::whereIn('field_id', [$model->getFieldName(), $model->getId()])
            ->where('modulename', $this->module)
            ->where('deleted', 0)
            ->first();
        return $relation->related_module ?? null;
    }
}
