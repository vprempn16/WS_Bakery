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
            'data.values.branchId' => ['required', 'string', 'exists:branches,id'],
            'data.values.reportDate' => ['required', 'date'],
            'data.values.notes' => ['nullable', 'string'],
            'data.values.items' => ['required', 'array', 'min:1'],
            'data.values.items.*.productId' => ['required', 'string', 'exists:products,id'],
            'data.values.items.*.quantitySold' => ['required', 'numeric', 'min:0'],
            'data.values.items.*.quantityReturned' => ['required', 'numeric', 'min:0'],
        ];
    }
}
