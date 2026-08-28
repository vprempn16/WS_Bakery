<?php

namespace App\Modules\Api\V1\MaterialIssue\Requests;

use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use App\Services\AuthUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMaterialIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.values.issueDate' => ['required', 'date'],
            'data.values.notes' => ['nullable', 'string'],
            'data.relatedRecords.items' => ['required', 'array', 'min:1'],
            'data.relatedRecords.items.*.ingredientId' => ['required', 'string', 'exists:ingredients,id', 'distinct'],
            'data.relatedRecords.items.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $items = $this->input('data.relatedRecords.items', []);
            $orgId = AuthUser::organizationId();

            foreach ($items as $index => $item) {
                $ingredientId = $item['ingredientId'] ?? null;
                if (!$ingredientId) {
                    continue;
                }

                $ingredient = Ingredient::where('organization_id', $orgId)->where('id', $ingredientId)->first();
                if (!$ingredient) {
                    $validator->errors()->add(
                        "data.relatedRecords.items.{$index}.ingredientId",
                        'Ingredient not found in your organization.'
                    );
                }
            }
        });
    }
}
