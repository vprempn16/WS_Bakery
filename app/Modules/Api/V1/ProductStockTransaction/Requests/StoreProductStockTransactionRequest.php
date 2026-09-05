<?php

namespace App\Modules\Api\V1\ProductStockTransaction\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductStockTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.values.productId' => ['required', 'string', 'exists:products,id'],
            'data.values.type' => ['required', 'in:in'],
            'data.values.quantity' => ['required', 'numeric', 'min:0.01'],
            'data.values.referenceNote' => ['nullable', 'string', 'max:255'],
        ];
    }
}
