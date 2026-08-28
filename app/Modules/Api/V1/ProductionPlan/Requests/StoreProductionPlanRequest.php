<?php

namespace App\Modules\Api\V1\ProductionPlan\Requests;

use App\Modules\Api\V1\Product\Models\Product;
use App\Services\AuthUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProductionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.values.planDate' => ['required', 'date'],
            'data.values.notes' => ['nullable', 'string'],
            'data.values.status' => ['sometimes', 'string', 'in:draft,approved'],
            'data.relatedRecords.items' => ['required', 'array', 'min:1'],
            'data.relatedRecords.items.*.productId' => ['required', 'string', 'exists:products,id', 'distinct'],
            'data.relatedRecords.items.*.plannedQuantity' => ['required', 'numeric', 'min:0.01'],
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
                }
            }
        });
    }
}
