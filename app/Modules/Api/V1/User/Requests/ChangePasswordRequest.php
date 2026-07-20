<?php

namespace App\Modules\Api\V1\User\Requests;

use App\Modules\Api\V1\User\Support\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.values.currentPassword' => ['required', 'string'],
            'data.values.password' => PasswordRules::strengthRules(true),
            'data.values.confirmPassword' => ['required', 'string', 'same:data.values.password'],
        ];
    }

    public function messages(): array
    {
        return array_merge(PasswordRules::messages(), [
            'data.values.currentPassword.required' => 'Current password is required.',
            'data.values.confirmPassword.same' => 'Passwords do not match.',
        ]);
    }
}
