<?php

namespace App\Modules\Api\V1\BranchTransfer\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Modules\Api\V1\Product\Models\Product;
use App\Services\AuthUser;

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
            'data.values.transferDate' => ['required', 'date'],
            'data.values.notes' => ['nullable', 'string'],
            'data.relatedRecords.items' => ['required', 'array', 'min:1'],
            'data.relatedRecords.items.*.productId' => ['required', 'string', 'exists:products,id', 'distinct'],
            'data.relatedRecords.items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'data.relatedRecords.items.*.unit' => ['required', 'string'],
            'data.relatedRecords.items.*.pieces' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $items = $this->input('data.relatedRecords.items', []);
            $orgId = AuthUser::organizationId();

            foreach ($items as $index => $item) {
                $productId = $item['productId'] ?? null;
                if (!$productId) {
                    continue;
                }

                $product = Product::where('organization_id', $orgId)->where('id', $productId)->first();
                if (!$product) {
                    $validator->errors()->add(
                        "data.relatedRecords.items.{$index}.productId",
                        'Product not found in your organization.'
                    );
                    continue;
                }

                $category = strtolower((string) ($product->category ?? ''));
                $needsPieces = $category !== 'spices';

                if ($needsPieces && (!isset($item['pieces']) || $item['pieces'] === '' || $item['pieces'] === null)) {
                    $validator->errors()->add(
                        "data.relatedRecords.items.{$index}.pieces",
                        'Pieces is required for this product category.'
                    );
                }
            }
        });
    }
}
