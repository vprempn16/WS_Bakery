<?php

namespace App\Modules\Api\V1\ProductionBatch\Resources;

use App\Services\ShelfLifeStatusService;
use App\Services\WarehouseExpiredStockService;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionBatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        $expiry = $this->expiry_timestamp;
        $productUnit = $this->product?->unit;
        $shelf = ShelfLifeStatusService::statusForTimestamp($expiry);
        $path = trim((string) ($request?->path() ?? ''), '/');
        $isListIndex = (bool) preg_match('#ProductionBatch/?$#i', $path);
        $context = WarehouseExpiredStockService::locationContext($this->resource, ! $isListIndex);
        $rawStatus = (string) ($this->status ?? '');
        $statusNorm = strtolower($rawStatus);

        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'organizationId_label' => $this->organization ? $this->organization->name : null,
            'batchNumber' => $this->batch_number,
            'productId' => $this->product_id,
            'productId_label' => $this->product
                ? trim(($this->product->name ?? '') . ($this->product->product_number ? ' (#' . $this->product->product_number . ')' : ''))
                : null,
            'productName' => $this->product?->name,
            'productUnit' => $productUnit,
            'quantityProduced' => (float) $this->quantity_produced,
            'pieces' => $this->pieces !== null ? (int) $this->pieces : null,
            'productionDate' => $this->production_date ? $this->production_date->format('Y-m-d H:i:s') : null,
            'expiryDate' => $expiry ? $expiry->format('Y-m-d') : null,
            'expiryTime' => $expiry ? $expiry->format('g:i a') : null,
            'expiryTimestamp' => $expiry ? $expiry->format('Y-m-d H:i:s') : null,
            'shelfStatus' => $shelf['shelfStatus'],
            'earliestExpiry' => $shelf['earliestExpiry'],
            'status' => $rawStatus,
            'statusLabel' => in_array($statusNorm, ['wasted', 'disposed'], true) ? 'Disposed' : $rawStatus,
            'notes' => $this->notes,
            'wastedAt' => $this->wasted_at ? $this->wasted_at->format('Y-m-d H:i:s') : null,
            'wastedQuantity' => $this->wasted_quantity !== null ? (float) $this->wasted_quantity : null,
            'wastedReason' => $this->wasted_reason,
            'currentLocation' => $context['currentLocation'],
            'locationType' => $context['locationType'],
            'canProcessExpiredStock' => $context['canProcessExpiredStock'],
            'canReturnExpiredStock' => $context['canReturnExpiredStock'],
            'expiredAtBranchId' => $context['expiredAtBranchId'],
            'expiredAtBranchName' => $context['expiredAtBranchName'],
            'expiredAtBranchQty' => $context['expiredAtBranchQty'],
            'warehouseExpiredQty' => $context['warehouseExpiredQty'],
            'warehouseName' => $context['warehouseName'],
            'createdAt' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
