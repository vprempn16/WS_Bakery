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
            'data.values.email' => ['required', 'email', 'string', 'max:255', 'unique:users,email'],
            'data.values.phone' => ['nullable', 'string', 'max:50'],
            'data.values.phoneNumber' => ['nullable', 'string', 'max:50'],
            'data.values.role' => ['nullable', 'string', 'max:255'],
            'data.values.roleId' => ['nullable'],
            'data.values.password' => ['required', 'string', 'min:6'],
            'data.values.confirmPassword' => ['required', 'string', 'same:data.values.password'],
            'data.values.is_active' => ['nullable'],
            'data.values.status' => ['nullable', 'string', 'in:Active,Inactive'],
            'data.values.branchId' => ['nullable'],
            'data.values.branch_id' => ['nullable'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $values = $this->input('data.values', []);
            $roleId = $values['roleId'] ?? null;
            if (is_array($roleId)) {
                $roleId = $roleId[0] ?? null;
            }
            $role = $values['role'] ?? null;
            $hasRoleId = $roleId !== null && $roleId !== '';
            $roleLooksLikeId = is_numeric($role) || (is_string($role) && ctype_digit((string) $role));

            if (! $hasRoleId && ! $roleLooksLikeId) {
                $validator->errors()->add('data.values.roleId', 'A Settings Role is required.');
            }
        });
    }
}
