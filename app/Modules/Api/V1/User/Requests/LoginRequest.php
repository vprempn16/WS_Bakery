<?php

namespace App\Modules\Api\V1\User\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.values.email' => ['required', 'email', 'string'],
            'data.values.password' => ['required', 'string'],
        ];
    }
}
