<?php

namespace App\Modules\Api\V1\Ingredient\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.values.organizationId' => ['required', 'string', 'exists:organizations,id'],
            'data.values.vendorId' => ['nullable', 'string', 'exists:vendors,id'],
            'data.values.name' => ['required', 'string', 'max:255'],
            'data.values.unit' => ['nullable', 'string', 'max:50'],
            'data.values.minimumStockLevel' => ['nullable', 'numeric', 'min:0'],
            'data.values.currentStock' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
