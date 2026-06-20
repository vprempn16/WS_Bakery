<?php

namespace App\Modules\Api\V1\ProductionBatch\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductionBatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     * Note: Expecting JSON structure: { "data": { "values": { ... } } }
     */
    public function rules(): array
    {
        return [
            'data.values.productId' => ['required', 'uuid', 'exists:products,id'],
            'data.values.quantityProduced' => ['required', 'numeric', 'min:0.01'],
            'data.values.productionDate' => ['required', 'date'],
            'data.values.notes' => ['nullable', 'string'],
        ];
    }
}
