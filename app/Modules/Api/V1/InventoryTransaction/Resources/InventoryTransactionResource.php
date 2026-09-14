<?php

namespace App\Modules\Api\V1\InventoryTransaction\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class InventoryTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'organizationId_label' => $this->organization ? $this->organization->name : null,
            'ingredientId' => $this->ingredient_id,
            'ingredientId_label' => $this->ingredient ? $this->ingredient->name : null,
            'unit' => $this->ingredient ? $this->ingredient->unit : null,
            'type' => $this->type,
            'quantity' => (float) $this->quantity,
            'referenceNote' => $this->reference_note,
            'createdAt' => $this->created_at
                ? (($this->created_at instanceof \DateTimeInterface)
                    ? $this->created_at->format(DATE_ATOM)
                    : Carbon::parse((string) $this->created_at)->toIso8601String())
                : null,
            'createdBy' => $this->created_by ?? null,
            'reversedAt' => $this->reversed_at
                ? (($this->reversed_at instanceof \DateTimeInterface)
                    ? $this->reversed_at->format(DATE_ATOM)
                    : Carbon::parse((string) $this->reversed_at)->toIso8601String())
                : null,
            'reversedBy' => $this->reversed_by ?? null,
            'reversalTransactionId' => $this->reversal_transaction_id ?? null,
        ];
    }
}
