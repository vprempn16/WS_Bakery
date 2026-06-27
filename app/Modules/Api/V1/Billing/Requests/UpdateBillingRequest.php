<?php

namespace App\Modules\Api\V1\Billing\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBillingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.values.branchId' => ['nullable', 'exists:branches,id'],
            'data.values.customerName' => ['nullable', 'string', 'max:255'],
            'data.values.customerPhone' => ['nullable', 'string', 'max:255'],
            'data.values.customerEmail' => ['nullable', 'email', 'max:255'],
            'data.values.discountAmount' => ['nullable', 'numeric', 'min:0'],
            'data.values.taxAmount' => ['nullable', 'numeric', 'min:0'],
            'data.values.paymentMethod' => ['nullable', 'string', 'in:Cash,Card,UPI'],
            'data.values.paymentStatus' => ['nullable', 'string', 'in:Paid,Pending,Cancelled'],
            
            'data.relatedRecords.items' => ['nullable', 'array'],
            'data.relatedRecords.items.*.id' => ['nullable', 'exists:billing_items,id'],
            'data.relatedRecords.items.*.productId' => ['required_with:data.relatedRecords.items', 'exists:products,id'],
            'data.relatedRecords.items.*.quantity' => ['required_with:data.relatedRecords.items', 'numeric', 'min:0.01'],
            'data.relatedRecords.items.*.unitPrice' => ['required_with:data.relatedRecords.items', 'numeric', 'min:0'],
        ];
    }
}
