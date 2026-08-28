<?php

namespace App\Modules\Api\V1\MaterialIssue\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMaterialIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.values.issueDate' => ['sometimes', 'date'],
            'data.values.notes' => ['nullable', 'string'],
            'data.values.status' => ['sometimes', 'string', 'in:posted,cancelled'],
        ];
    }
}
