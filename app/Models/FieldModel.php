<?php

namespace App\Models;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FieldModel
{
    protected $data;

    public function __construct($crmFieldRow)
    {
        if (!isset($crmFieldRow->is_custom_field)) {
            $crmFieldRow->is_custom_field = 0;
        }
        $this->data = $crmFieldRow;
    }

    public function getAPIName(): string
    {
        return $this->data->apifieldname ?? $this->data->fieldname;
    }

    public function getId(): string
    {
        return $this->data->id;
    }

    public function getDisplaytype() 
    {
        return $this->data->displaytype;
    }

    public function getFieldName(): string
    {
        return $this->data->fieldname;
    }

    public function getTableName(): string
    {
        return $this->data->tablename ?? '';
    }

    public function getLabel(): string
    {
        return $this->data->fieldlabel;
    }

    public function getFieldType(): string
    {
        return $this->data->fieldtype;
    }

    public function isMandatory(): bool
    {
        return $this->data->mandatory;
    }

    public function isCustomField(): bool
    {
        return (bool) $this->data->is_custom_field;
    }

    public function validate($value)
    {
        $type = strtolower($this->getFieldType());
        $apiField = $this->getAPIName();
        $label = $this->getLabel();

        if ($type === 'multiselect' && is_array($value)) {
            $value = implode(',', array_map('strval', $value));
        }

        if (in_array($type, ['picklist', 'multiselect'], true)) {
            if (($value === null || $value === '') && !$this->isMandatory()) {
                // allowed
            } else {
                $options = \DB::table('picklist_values')
                    ->where('field_id', $this->getId())
                    ->where('status', 1)
                    ->pluck('value')
                    ->toArray();
                $optionsLower = array_map('strtolower', $options);
                if ($type === 'multiselect') {
                    $vals = is_string($value) ? array_map('trim', explode(',', $value)) : (is_array($value) ? $value : []);
                    $vals = array_filter($vals);
                    foreach ($vals as $v) {
                        if (!in_array(strtolower((string) $v), $optionsLower)) {
                            throw ValidationException::withMessages([$apiField => ["Invalid value for {$label}"]]);
                        }
                    }
                } else {
                    if (!in_array(strtolower((string) $value), $optionsLower)) {
                        throw ValidationException::withMessages([$apiField => ["Invalid value for {$label}"]]);
                    }
                }
            }
        }

        $rules = [];
        if ($this->isMandatory()) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        if (in_array($this->getFieldName(), ['id', 'organization_id', 'created_at', 'updated_at'], true)) {
            return $value;
        }

        switch ($type) {
            case 'email': $rules[] = 'email'; break;
            case 'integer': 
            case 'number': 
            case 'decimal': $rules[] = 'numeric'; break;
            case 'date': 
            case 'datetime': 
            case 'timestamp': $rules[] = 'date'; break;
            case 'boolean': 
            case 'checkbox': $rules[] = 'boolean'; break;
            case 'phone': $rules[] = 'regex:/^[0-9+\-\s]{6,20}$/'; break;
            case 'uuid': 
            case 'relation': 
            case 'relationpicklist': 
            case 'multirelationpicklist': $rules[] = 'uuid'; break;
            case 'picklist': 
            case 'multiselect': 
            case 'string': 
            case 'text': 
            case 'textarea': $rules[] = 'string'; break;
            default: $rules[] = 'string'; break;
        }

        $validator = Validator::make(
            [$apiField => $value],
            [$apiField => $rules],
            [$apiField . '.required' => "{$label} is required."]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        if (in_array($type, ['decimal', 'integer', 'number'], true)) {
            if ($value === null || $value === '' || $value === false) {
                $value = 0;
            } else {
                if ($type === 'decimal') {
                    $value = number_format((float) $value, 2, '.', '');
                } else {
                    $value = (int) $value;
                }
            }
        }

        return $value ?? '';
    }
}
