<?php

namespace App\Modules\Api\V1\Organization\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationRequest extends FormRequest
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
            'data.values.firstUser.firstName' => ['required', 'string', 'max:255'],
            'data.values.firstUser.lastName' => ['required', 'string', 'max:255'],
            'data.values.firstUser.email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'data.values.firstUser.phoneNumber' => ['nullable', 'string', 'max:50'],
            'data.values.firstUser.password' => ['required', 'string', 'min:8'],
            'data.values.firstUser.confirmPassword' => ['required', 'same:data.values.firstUser.password'],
        ];
    }
}
