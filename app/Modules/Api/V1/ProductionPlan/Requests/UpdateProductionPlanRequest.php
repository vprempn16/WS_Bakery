<?php

namespace App\Modules\Api\V1\ProductionPlan\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.values.planDate' => ['sometimes', 'date'],
            'data.values.notes' => ['nullable', 'string'],
            'data.relatedRecords.items' => ['sometimes', 'array', 'min:1'],
            'data.relatedRecords.items.*.productId' => ['required_with:data.relatedRecords.items', 'string', 'exists:products,id', 'distinct'],
            'data.relatedRecords.items.*.plannedQuantity' => ['required_with:data.relatedRecords.items', 'numeric', 'min:0.01'],
            'data.relatedRecords.items.*.producedQuantity' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
