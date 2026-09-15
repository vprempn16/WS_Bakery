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
            'data.values.productNumber' => ['required', 'string', 'max:255', 'regex:/^\d+$/'],
            'data.values.name' => ['required', 'string', 'max:255'],
            'data.values.description' => ['nullable', 'string'],
            'data.values.productImage' => ['nullable'],
            'data.values.price' => ['nullable', 'numeric', 'min:0'],
            'data.values.unit' => ['required', 'string', 'in:gm,pcs,ml'],
            'data.values.category' => ['required', 'string', 'max:100'],
            'data.values.status' => ['nullable', 'string', 'in:active,inactive'],
            'data.values.productSource' => ['nullable', 'string', 'in:own,bought'],
            'data.values.shelfLife' => ['nullable', 'integer', 'min:1', 'max:87600'],
            'data.values.expiryDate' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'data.values.productNumber.required' => 'Product number is required.',
            'data.values.productNumber.regex' => 'Product number must contain digits only (no letters).',
            'data.values.category.required' => 'Category is required.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $productNumber = $this->input('data.values.productNumber');
            if ($productNumber !== null && trim((string) $productNumber) !== '') {
                $orgId = AuthUser::organizationId();
                if ($orgId) {
                    $excludeId = $this->route('id');
                    $check = ProductNumberService::checkAvailability(
                        $orgId,
                        (string) $productNumber,
                        $excludeId ? (string) $excludeId : null
                    );

                    if (! $check['available']) {
                        $validator->errors()->add(
                            'data.values.productNumber',
                            $check['message'] ?? 'Product number already exists'
                        );
                    }
                }
            }

            $source = strtolower(trim((string) ($this->input('data.values.productSource') ?? 'own')));
            $isBought = $source === 'bought';
            $shelfLife = $this->input('data.values.shelfLife');
            $expiryDate = $this->input('data.values.expiryDate');

            if (! $isBought && ($shelfLife === null || $shelfLife === '' || (int) $shelfLife <= 0)) {
                $validator->errors()->add(
                    'data.values.shelfLife',
                    'Shelf life is required for own (baked) products.'
                );
            }

            if ($isBought && ($expiryDate === null || trim((string) $expiryDate) === '')) {
                $validator->errors()->add(
                    'data.values.expiryDate',
                    'Expired date is required for bought products.'
                );
            }
        });
    }
}
