<?php

namespace App\Modules\Api\V1\Organization\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.values.name' => ['required', 'string', 'max:255'],
            'data.values.description' => ['nullable', 'string'],
            'data.values.email' => ['nullable', 'email', 'string', 'max:255'],
            'data.values.phone' => ['nullable', 'string', 'max:50'],
            'data.values.address' => ['nullable', 'string'],
        ];
    }
}
