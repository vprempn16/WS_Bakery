<?php

namespace App\Modules\Api\V1\Branch\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.values.name' => 'required|string|max:255',
            'data.values.type' => 'required|in:warehouse,retail',
            'data.values.address' => 'nullable|string',
            'data.values.phone' => 'nullable|string|max:20',
        ];
    }
}
