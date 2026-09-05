<?php

namespace App\Modules\Api\V1\SavedFilter\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSavedFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.values.name' => ['required', 'string', 'max:255'],
            'data.values.isPublic' => ['nullable', 'boolean'],
            'data.values.rules' => ['nullable', 'array'],
            'data.values.rules.logical_operator' => ['nullable', 'string', 'in:AND,OR,and,or'],
            'data.values.rules.conditions' => ['nullable', 'array'],
            'data.values.rules.conditions.*.field' => ['required', 'string'],
            'data.values.rules.conditions.*.operator' => ['required', 'string', 'in:=,!=,>,<,>=,<=,like,LIKE,in,IN'],
            'data.values.rules.conditions.*.value' => ['required'],
            'data.values.headerDetails' => ['required', 'array', 'min:1'],
            'data.values.headerDetails.*.fieldname' => ['required', 'string'],
            'data.values.headerDetails.*.fieldlabel' => ['required', 'string'],
        ];
    }
}
