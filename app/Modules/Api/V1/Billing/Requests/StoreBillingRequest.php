<?php

namespace App\Modules\Api\V1\Billing\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Modules\Api\V1\Billing\Models\Billing;

class StoreBillingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.values.branchId' => ['required', 'exists:branches,id'],
            // 'data.values.customerName' => ['nullable', 'string', 'max:255'],
            // 'data.values.customerPhone' => ['nullable', 'string', 'max:255'],
            // 'data.values.customerEmail' => ['nullable', 'email', 'max:255'],
            'data.values.discountAmount' => ['nullable', 'numeric', 'min:0'],
            'data.values.taxAmount' => ['nullable', 'numeric', 'min:0'],
            'data.values.paymentMethod' => ['required', 'string', 'in:cash,card,upi'],
            'data.values.paymentStatus' => ['required', 'string', 'in:paid,pending,cancelled'],
            
            'data.relatedRecords.items' => ['required', 'array', 'min:1'],
            'data.relatedRecords.items.*.productId' => ['required', 'exists:products,id'],
            'data.relatedRecords.items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'data.relatedRecords.items.*.unitPrice' => ['required', 'numeric', 'min:0'],
            'data.relatedRecords.items.*.unit' => ['required', 'string'],
            'data.relatedRecords.items.*.category' => ['required', 'string'],
        ];
    }
}
