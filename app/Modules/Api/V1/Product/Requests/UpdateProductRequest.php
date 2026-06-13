<?php

namespace App\Modules\Api\V1\Product\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.values.organizationId' => ['required', 'string', 'exists:organizations,id'],
            'data.values.name' => ['required', 'string', 'max:255'],
            'data.values.description' => ['nullable', 'string'],
            'data.values.price' => ['nullable', 'numeric', 'min:0'],
            'data.values.unit' => ['nullable', 'string', 'in:pcs,kg,g,l,ml,pkt'],
            'data.values.shelfLifeDays' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
