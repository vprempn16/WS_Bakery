<?php

namespace App\Modules\Api\V1\User\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.values.firstName' => ['required', 'string', 'max:255'],
            'data.values.lastName' => ['required', 'string', 'max:255'],
            'data.values.role' => ['required', 'string', 'max:255'],
            'data.values.email' => ['required', 'email', 'string', 'max:255', 'unique:users,email'],
            'data.values.phone' => ['nullable', 'string', 'max:50'],
            'data.values.password' => ['required', 'string', 'min:6'],
            'data.values.confirmPassword' => ['required', 'string', 'same:data.values.password'],
            'data.values.organizationId' => ['required', 'uuid', 'exists:organizations,id'],
        ];
    }
}
