<?php

namespace App\Modules\Api\V1\BranchTransfer\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBranchTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.values.branchId' => ['required', 'string', 'exists:branches,id'],
            'data.values.productId' => ['required', 'string', 'exists:products,id'],
            'data.values.quantity' => ['required', 'numeric', 'min:0.01'],
            'data.values.transferDate' => ['required', 'date'],
            'data.values.notes' => ['nullable', 'string'],
        ];
    }
}
