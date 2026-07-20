<?php

namespace App\Modules\Api\V1\User\Requests;

use App\Modules\Api\V1\User\Support\PasswordRules;
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
            'data.values.role' => ['nullable'], // Settings Role id (int) or bakery role string
            'data.values.roleId' => ['nullable'],
            'data.values.password' => PasswordRules::strengthRules(false),
            'data.values.confirmPassword' => ['nullable', 'required_with:data.values.password', 'string', 'same:data.values.password'],
            'data.values.is_active' => ['nullable'],
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
}
