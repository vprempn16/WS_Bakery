<?php

namespace App\Modules\Api\V1\Product\Requests;

use App\Modules\Api\V1\Product\Services\ProductNumberService;
use App\Services\AuthUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.values.productNumber' => ['nullable', 'string', 'max:255', 'regex:/^\d+$/'],
            'data.values.name' => ['required', 'string', 'max:255'],
            'data.values.description' => ['nullable', 'string'],
            'data.values.price' => ['nullable', 'numeric', 'min:0'],
            'data.values.unit' => ['required', 'string', 'in:gm,pcs,ml'],
            'data.values.category' => ['nullable', 'string', 'in:bread,sweet,cake,snack,spices,beverage,other'],
            'data.values.status' => ['nullable', 'string', 'in:active,inactive'],
            'data.values.shelfLife' => ['nullable', 'integer', 'in:6,12,24,48,72,120,168,336,720'],
            'data.values.tier' => ['nullable', 'string', 'in:tier_1,tier_2,tier_3'],
        ];
    }

    public function messages(): array
    {
        return [
            'data.values.productNumber.regex' => 'Product number must contain digits only (no letters).',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $productNumber = $this->input('data.values.productNumber');
            if ($productNumber === null || trim((string) $productNumber) === '') {
                return;
            }

            $orgId = AuthUser::organizationId();
            if (!$orgId) {
                return;
            }

            $excludeId = $this->route('id');
            $check = ProductNumberService::checkAvailability(
                $orgId,
                (string) $productNumber,
                $excludeId ? (string) $excludeId : null
            );

            if (!$check['available']) {
                $validator->errors()->add(
                    'data.values.productNumber',
                    $check['message'] ?? 'Product number already exists'
                );
            }
        });
    }
}
