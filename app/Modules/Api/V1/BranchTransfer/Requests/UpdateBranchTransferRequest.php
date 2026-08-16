<?php

namespace App\Modules\Api\V1\BranchTransfer\Requests;

use App\Modules\Api\V1\Product\Models\Product;
use App\Services\AuthUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateBranchTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.values.transferDate' => ['sometimes', 'required', 'date'],
            'data.values.notes' => ['nullable', 'string'],
            'data.values.status' => [
                'sometimes',
                'string',
                'in:pending,dispatched,received,cancelled,Pending,Dispatched,Received,Cancelled,completed,Completed',
            ],
            // branchId must never be updated after create
            'data.values.branchId' => ['prohibited'],
            // Line items may be synced while pending (validated further in controller by status).
            'data.relatedRecords.items' => ['sometimes', 'array', 'min:1'],
            'data.relatedRecords.items.*.productId' => ['required_with:data.relatedRecords.items', 'string', 'exists:products,id', 'distinct'],
            'data.relatedRecords.items.*.quantity' => ['required_with:data.relatedRecords.items', 'numeric', 'min:0.01'],
            'data.relatedRecords.items.*.unit' => ['nullable', 'string'],
            'data.relatedRecords.items.*.pieces' => ['nullable', 'numeric', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->has('data.relatedRecords.items')) {
                return;
            }

            $items = $this->input('data.relatedRecords.items', []);
            $orgId = AuthUser::organizationId();

            foreach ($items as $index => $item) {
                $productId = $item['productId'] ?? null;
                if (! $productId) {
                    continue;
                }

                $product = Product::where('organization_id', $orgId)->where('id', $productId)->first();
                if (! $product) {
                    $validator->errors()->add(
                        "data.relatedRecords.items.{$index}.productId",
                        'Product not found in your organization.'
                    );
                    continue;
                }

                $unit = strtolower(trim((string) ($product->unit ?? '')));
                $isPieceUnit = in_array($unit, ['pcs', 'pc', 'piece', 'pieces'], true);
                $piecesProvided = array_key_exists('pieces', $item)
                    && $item['pieces'] !== null
                    && $item['pieces'] !== '';

                if ($isPieceUnit) {
                    if (! $piecesProvided) {
                        $validator->errors()->add(
                            "data.relatedRecords.items.{$index}.pieces",
                            'Pieces is required when product unit is pcs.'
                        );
                    } elseif (! is_numeric($item['pieces']) || (float) $item['pieces'] < 1 || floor((float) $item['pieces']) != (float) $item['pieces']) {
                        $validator->errors()->add(
                            "data.relatedRecords.items.{$index}.pieces",
                            'Pieces must be a whole number of at least 1.'
                        );
                    }
                } elseif ($piecesProvided) {
                    if (! is_numeric($item['pieces']) || (float) $item['pieces'] < 1 || floor((float) $item['pieces']) != (float) $item['pieces']) {
                        $validator->errors()->add(
                            "data.relatedRecords.items.{$index}.pieces",
                            'Pieces must be a whole number of at least 1.'
                        );
                    }
                }
            }
        });
    }

    /**
     * Only allow whitelisted editable fields through to the controller.
     */
    public function safeValues(): array
    {
        $values = $this->input('data.values') ?? [];

        return array_intersect_key($values, array_flip(['transferDate', 'notes', 'status']));
    }

    /**
     * @return array<int, array{productId:string, quantity:float|int|string, unit?:string|null, pieces?:float|int|string|null}>|null
     */
    public function safeItems(): ?array
    {
        if (! $this->has('data.relatedRecords.items')) {
            return null;
        }

        $items = $this->input('data.relatedRecords.items', []);
        if (! is_array($items)) {
            return null;
        }

        return array_values($items);
    }
}
