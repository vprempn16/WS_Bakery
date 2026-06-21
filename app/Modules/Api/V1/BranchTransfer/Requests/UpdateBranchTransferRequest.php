<?php

namespace App\Modules\Api\V1\BranchTransfer\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBranchTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.values.transferDate' => ['required', 'date'],
            'data.values.notes' => ['nullable', 'string'],
        ];
    }
}
