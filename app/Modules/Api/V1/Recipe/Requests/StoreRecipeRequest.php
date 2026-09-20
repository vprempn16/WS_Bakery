<?php

namespace App\Modules\Api\V1\Recipe\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'data.values.quantityPending' => ['sometimes', 'boolean'],
            'data.values.quantityRequired' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $values = $this->input('data.values', []);
            $pending = filter_var($values['quantityPending'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $qty = $values['quantityRequired'] ?? null;

            if ($pending) {
                return;
            }

            if ($qty === null || $qty === '' || (float) $qty < 0.01) {
                $validator->errors()->add(
                    'data.values.quantityRequired',
                    'Quantity is required unless marked as pending.'
                );
            }
        });
    }
}
