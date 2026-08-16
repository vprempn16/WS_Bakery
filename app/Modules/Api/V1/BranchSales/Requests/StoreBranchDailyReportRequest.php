<?php

namespace App\Modules\Api\V1\BranchSales\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBranchDailyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Branch comes from the authenticated X-Branch-Id context. The payload
            // field remains optional for backward compatibility with older clients.
            'data.values.branchId' => ['nullable', 'string', 'exists:branches,id'],
            'data.values.reportDate' => ['required', 'date'],
            'data.values.notes' => ['nullable', 'string'],
            // When omitted, items are generated from paid POS bills for the date.
            'data.values.items' => ['sometimes', 'array'],
            'data.values.items.*.productId' => ['required', 'string', 'exists:products,id'],
            'data.values.items.*.quantitySold' => ['required', 'numeric', 'min:0'],
            'data.values.items.*.quantityReturned' => ['required', 'numeric', 'min:0'],
        ];
    }
}
