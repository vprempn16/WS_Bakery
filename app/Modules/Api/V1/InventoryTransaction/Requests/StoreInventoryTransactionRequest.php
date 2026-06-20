<?php

namespace App\Modules\Api\V1\InventoryTransaction\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.values.ingredientId' => ['required', 'string', 'exists:ingredients,id'],
            'data.values.type' => ['required', 'in:in,out,waste,production'],
            'data.values.quantity' => ['required', 'numeric', 'min:0.01'],
            'data.values.referenceNote' => ['nullable', 'string', 'max:255'],
        ];
    }
}
