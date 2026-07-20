<?php

namespace App\Modules\Api\V1\User\Requests;

use App\Modules\Api\V1\User\Support\PasswordRules;
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
            'data.values.role' => ['nullable'], // Settings Role id (int) or bakery role string
            'data.values.roleId' => ['nullable'],
            'data.values.password' => PasswordRules::strengthRules(true),
            'data.values.confirmPassword' => ['required', 'string', 'same:data.values.password'],
            'data.values.is_active' => ['nullable'],
            // FE sends 0|1 checkbox; also accept Active|Inactive for older clients
            'data.values.status' => ['nullable'],
            'data.values.branchId' => ['nullable'],
            'data.values.branch_id' => ['nullable'],
            'data.values.organizationId' => ['nullable'],
            'data.values.agreeUpdates' => ['nullable'],
            'data.values.profileImage' => ['nullable'],
        ];
    }

    public function messages(): array
    {
        return array_merge(PasswordRules::messages(), [
            'data.values.confirmPassword.same' => 'Passwords do not match.',
        ]);
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
