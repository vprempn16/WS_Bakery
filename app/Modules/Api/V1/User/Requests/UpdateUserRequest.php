<?php

namespace App\Modules\Api\V1\User\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'data.values.firstName' => ['required', 'string', 'max:255'],
            'data.values.lastName' => ['required', 'string', 'max:255'],
            'data.values.email' => [
                'required',
                'email',
                'string',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'data.values.phone' => ['nullable', 'string', 'max:50'],
            'data.values.phoneNumber' => ['nullable', 'string', 'max:50'],
            'data.values.role' => ['nullable', 'string', 'max:255'],
            'data.values.roleId' => ['nullable'],
            'data.values.password' => ['nullable', 'string', 'min:6'],
            'data.values.confirmPassword' => ['nullable', 'required_with:data.values.password', 'string', 'same:data.values.password'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $values = $this->input('data.values', []);
            $role = $values['role'] ?? null;
            if ($role === null && isset($values['roleId'])) {
                $role = is_array($values['roleId']) ? ($values['roleId'][0] ?? null) : $values['roleId'];
            }
            if ($role === null || $role === '') {
                $validator->errors()->add('data.values.role', 'The role field is required.');
            }
        });
    }
}
