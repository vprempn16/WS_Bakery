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
            'data.values.is_active' => ['nullable'],
            'data.values.status' => ['nullable', 'string', 'in:Active,Inactive'],
            'data.values.branchId' => ['nullable'],
            'data.values.branch_id' => ['nullable'],
        ];
    }
}
