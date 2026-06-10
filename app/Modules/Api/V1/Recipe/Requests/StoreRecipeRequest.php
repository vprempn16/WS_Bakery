<?php

namespace App\Modules\Api\V1\Recipe\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.values.ingredientId' => ['required', 'string', 'exists:ingredients,id'],
            'data.values.quantityRequired' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
