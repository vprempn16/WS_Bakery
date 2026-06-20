<?php

namespace App\Modules\Api\V1\Vendor\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.values.name' => ['required', 'string', 'max:255'],
            'data.values.contactPerson' => ['nullable', 'string', 'max:255'],
            'data.values.phone' => ['nullable', 'string', 'max:50'],
            'data.values.email' => ['nullable', 'email', 'string', 'max:255'],
            'data.values.address' => ['nullable', 'string'],
        ];
    }
}
