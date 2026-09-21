<?php

namespace App\Modules\Api\V1\BranchTransfer\Services;

use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use App\Modules\Api\V1\BranchTransfer\Models\BranchTransfer;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\Branch\Models\Branch;
use App\Services\ShelfLifeStatusService;
use App\Services\WarehouseExpiredStockService;

class BranchTransferStockService
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Soft-check warehouse availability + destination-only expiry without locking (create-time).
     *
     * @param  array<int, array{productId:string, quantity:float|int|string}>  $items
     */
    public function assertTransferAllowed(string $orgId, string $destinationBranchId, array $items): void
    {
        $this->assertWarehouseAvailability($orgId, $items);
        $this->assertDestinationHasNoExpiredProductStock($orgId, $destinationBranchId, $items);
    }

    /**
     * Soft-check warehouse availability without locking (create-time validation).
     *
     * @param  array<int, array{productId:string, quantity:float|int|string}>  $items
     */
    public function assertWarehouseAvailability(string $orgId, array $items): void
    {
        foreach ($items as $item) {
            $productId = (string) ($item['productId'] ?? '');
            $quantity = (float) ($item['quantity'] ?? 0);
            if ($productId === '' || $quantity <= 0) {
                continue;
            }

            $product = Product::where('organization_id', $orgId)
                ->where('id', $productId)
                ->firstOrFail();

            if (! $product->isSellable()) {
                throw new \RuntimeException(
                    "Product \"{$product->name}\" is inactive and cannot be transferred."
                );
            }

            $onHand = WarehouseExpiredStockService::warehouseOnHand($orgId, $productId, true);
            if ($onHand < $quantity) {
                throw new \RuntimeException(
                    "Insufficient warehouse stock for {$product->name}. Available: {$onHand}, requested: {$quantity}."
                );
            }

            $this->assertFreshWarehouseAvailable($product, $quantity);
        }
    }

    public function normalizeStatus(?string $status): string
    {
        return strtolower(trim((string) $status));
    }

    public function isReceivedLike(?string $status): bool
    {
        $s = $this->normalizeStatus($status);

        return in_array($s, [self::STATUS_RECEIVED, self::STATUS_COMPLETED], true);
    }

    /**
     * Apply a status transition with the correct stock mutations.
     *
     * @return string Human-readable success message
     */
    public function transition(BranchTransfer $transfer, string $toStatus): string
    {
        $from = $this->normalizeStatus($transfer->status);
        $to = $this->normalizeStatus($toStatus);

        if ($from === self::STATUS_CANCELLED) {
            throw new \RuntimeException('Cancelled transfers cannot change status.');
        }

        if ($to === self::STATUS_CANCELLED) {
            $this->cancel($transfer);

            return 'Transfer cancelled and stock reversed.';
        }

        if ($to === self::STATUS_DISPATCHED) {
            if ($from !== self::STATUS_PENDING) {
                throw new \RuntimeException('Only pending transfers can be dispatched.');
            }
            $this->deductWarehouse($transfer);
            $transfer->status = self::STATUS_DISPATCHED;
            $transfer->save();

            return 'Transfer dispatched. Warehouse stock deducted.';
        }

        if ($to === self::STATUS_RECEIVED) {
            if ($from !== self::STATUS_DISPATCHED) {
                throw new \RuntimeException('Only dispatched transfers can be received.');
            }
            $this->creditBranch($transfer);
            $transfer->status = self::STATUS_RECEIVED;
            $transfer->save();

            return 'Transfer received. Branch stock credited.';
        }

        throw new \RuntimeException("Unsupported transfer status transition: {$from} → {$to}.");
    }

    public function cancel(BranchTransfer $transfer): void
    {
        $status = $this->normalizeStatus($transfer->status);

        if ($status === self::STATUS_CANCELLED) {
            throw new \RuntimeException('Transfer is already cancelled.');
        }

        if ($status === self::STATUS_PENDING) {
            // Nothing reserved yet.
        } elseif ($status === self::STATUS_DISPATCHED) {
            $this->reverseDispatched($transfer);
        } elseif ($this->isReceivedLike($status)) {
            $this->reverseReceived($transfer);
        } else {
            throw new \RuntimeException("Cannot cancel transfer in status \"{$status}\".");
        }

        $transfer->status = self::STATUS_CANCELLED;
        $transfer->save();
    }

    public function deductWarehouse(BranchTransfer $transfer): void
    {
        $orgId = (string) $transfer->organization_id;
        $transfer->loadMissing('items');

        foreach ($transfer->items as $item) {
            $qty = (float) $item->quantity;
            $product = Product::where('organization_id', $orgId)
                ->where('id', $item->product_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $product->isSellable()) {
                throw new \RuntimeException(
                    "Product \"{$product->name}\" is inactive and cannot be transferred."
                );
            }

            WarehouseExpiredStockService::forgetCache();
            $onHand = WarehouseExpiredStockService::warehouseOnHand($orgId, (string) $item->product_id, true);
            $product->refresh();

            if ($onHand < $qty) {
                throw new \RuntimeException(
                    "Insufficient warehouse stock for {$product->name}. Available: {$onHand}, requested: {$qty}."
                );
            }

            $this->assertFreshWarehouseAvailable($product, $qty);
            $this->assertDestinationHasNoExpiredProductStock(
                $orgId,
                (string) $transfer->branch_id,
                [['productId' => (string) $item->product_id, 'quantity' => $qty]]
            );

            $product->current_stock = (float) $product->current_stock - $qty;
            $product->save();
        }
    }

    public function creditBranch(BranchTransfer $transfer): void
    {
        $orgId = (string) $transfer->organization_id;
        $branchId = (string) $transfer->branch_id;
        $transfer->loadMissing('items');

        foreach ($transfer->items as $item) {
            $qty = (float) $item->quantity;
            $productId = (string) $item->product_id;

            $branchStock = BranchStock::where('organization_id', $orgId)
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (! $branchStock) {
                try {
                    $branchStock = BranchStock::create([
                        'organization_id' => $orgId,
                        'branch_id' => $branchId,
                        'product_id' => $productId,
                        'current_stock' => 0,
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    $branchStock = BranchStock::where('organization_id', $orgId)
                        ->where('branch_id', $branchId)
                        ->where('product_id', $productId)
                        ->firstOrFail();
                }
                $branchStock = BranchStock::where('id', $branchStock->id)->lockForUpdate()->first();
            }

            $branchStock->current_stock = (float) $branchStock->current_stock + $qty;
            $branchStock->save();
        }
    }

    public function reverseDispatched(BranchTransfer $transfer): void
    {
        $orgId = (string) $transfer->organization_id;
        $transfer->loadMissing('items');

        foreach ($transfer->items as $item) {
            $qty = (float) $item->quantity;
            $product = Product::where('organization_id', $orgId)
                ->where('id', $item->product_id)
                ->lockForUpdate()
                ->firstOrFail();
            $product->current_stock = (float) $product->current_stock + $qty;
            $product->save();
        }
    }

    public function reverseReceived(BranchTransfer $transfer): void
    {
        $orgId = (string) $transfer->organization_id;
        $branchId = (string) $transfer->branch_id;
        $transfer->loadMissing('items');

        foreach ($transfer->items as $item) {
            $qty = (float) $item->quantity;

            $branchStock = BranchStock::where('organization_id', $orgId)
                ->where('branch_id', $branchId)
                ->where('product_id', $item->product_id)
                ->lockForUpdate()
                ->first();

            if (! $branchStock || (float) $branchStock->current_stock < $qty) {
                throw new \RuntimeException(
                    'Cannot reverse transfer: branch stock is insufficient (already sold).'
                );
            }

            $branchStock->current_stock = (float) $branchStock->current_stock - $qty;
            $branchStock->save();

            $product = Product::where('organization_id', $orgId)
                ->where('id', $item->product_id)
                ->lockForUpdate()
                ->firstOrFail();
            $product->current_stock = (float) $product->current_stock + $qty;
            $product->save();
        }
    }

    /**
     * Transfers may only move fresh warehouse on-hand (ledger − expiredQty).
     */
    private function assertFreshWarehouseAvailable(Product $product, float $qty): void
    {
        $orgId = (string) $product->organization_id;
        $productId = (string) $product->id;
        $ledger = (float) $product->current_stock;
        $shelf = ShelfLifeStatusService::statusForProducts($orgId, [$productId], [$productId => $ledger]);
        $expired = (float) ($shelf[$productId]['expiredQty'] ?? 0);
        $fresh = max(0.0, round($ledger - $expired, 2));
        if ($qty > $fresh + 0.001) {
            $warehouseName = WarehouseExpiredStockService::warehouseNameForOrg($orgId);
            throw new \RuntimeException(
                "Cannot transfer expired batch: {$product->name} — expired stock remains in {$warehouseName}. Select a fresh production quantity before transferring. Available fresh: {$fresh}, requested: {$qty}."
            );
        }
    }

    /**
     * Block transfer when the selected destination (only) has expired on-hand of the same product.
     *
     * @param  array<int, array{productId?:string, quantity?:float|int|string}>  $items
     */
    public function assertDestinationHasNoExpiredProductStock(string $orgId, string $destinationBranchId, array $items): void
    {
        $destination = Branch::query()
            ->where('organization_id', $orgId)
            ->where('id', $destinationBranchId)
            ->first();
        $destName = $destination?->name ?: 'the destination';

        $productIds = [];
        foreach ($items as $item) {
            $productId = (string) ($item['productId'] ?? '');
            if ($productId !== '') {
                $productIds[] = $productId;
            }
        }
        $productIds = array_values(array_unique($productIds));
        if ($productIds === []) {
            return;
        }

        $stocks = BranchStock::query()
            ->where('organization_id', $orgId)
            ->where('branch_id', $destinationBranchId)
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'current_stock'])
            ->keyBy(fn ($row) => (string) $row->product_id);

        $products = Product::query()
            ->where('organization_id', $orgId)
            ->whereIn('id', $productIds)
            ->get(['id', 'name', 'unit'])
            ->keyBy(fn ($p) => (string) $p->id);

        foreach ($productIds as $productId) {
            $destStock = (float) ($stocks->get($productId)?->current_stock ?? 0);
            if ($destStock <= 0.001) {
                continue;
            }

            $product = $products->get($productId);
            $name = $product?->name ?: 'This product';
            $unit = $product?->unit ?: 'pcs';

            $warehouse = WarehouseExpiredStockService::warehouseShelfForProduct($orgId, $productId);
            $warehouseFresh = max(0.0, round((float) $warehouse['currentStock'] - (float) $warehouse['expiredQty'], 2));

            $shelf = ShelfLifeStatusService::statusForProducts(
                $orgId,
                [$productId],
                [$productId => $destStock],
                24,
                [$productId => $warehouseFresh]
            );
            $expired = (float) ($shelf[$productId]['expiredQty'] ?? 0);
            if ($expired <= 0.001) {
                continue;
            }

            $qtyLabel = rtrim(rtrim(number_format($expired, 2, '.', ''), '0'), '.');

            throw new \RuntimeException(
                "Expired {$name} stock found in {$destName}. {$destName} has {$qtyLabel} {$unit} of expired {$name}. Process the expired stock from {$destName} before transferring additional {$name}."
            );
        }
    }

    /**
     * Destination-only expired qty preview for the transfer form.
     *
     * @param  array<int, string>  $productIds
     * @return array{
     *   branchId: string,
     *   branchName: string,
     *   products: list<array{productId: string, name: string, unit: ?string, expiredQty: float, currentStock: float}>
     * }
     */
    public function destinationExpiryPreview(string $orgId, string $destinationBranchId, array $productIds): array
    {
        $destination = Branch::query()
            ->where('organization_id', $orgId)
            ->where('id', $destinationBranchId)
            ->firstOrFail();

        $productIds = array_values(array_unique(array_filter(array_map('strval', $productIds))));
        $products = [];
        if ($productIds === []) {
            return [
                'branchId' => (string) $destination->id,
                'branchName' => (string) ($destination->name ?: 'Retail branch'),
                'products' => [],
            ];
        }

        $stocks = BranchStock::query()
            ->where('organization_id', $orgId)
            ->where('branch_id', $destinationBranchId)
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'current_stock'])
            ->keyBy(fn ($row) => (string) $row->product_id);

        $meta = Product::query()
            ->where('organization_id', $orgId)
            ->whereIn('id', $productIds)
            ->get(['id', 'name', 'unit'])
            ->keyBy(fn ($p) => (string) $p->id);

        foreach ($productIds as $productId) {
            $destStock = (float) ($stocks->get($productId)?->current_stock ?? 0);
            $expired = 0.0;
            if ($destStock > 0.001) {
                $warehouse = WarehouseExpiredStockService::warehouseShelfForProduct($orgId, $productId);
                $warehouseFresh = max(0.0, round((float) $warehouse['currentStock'] - (float) $warehouse['expiredQty'], 2));
                $shelf = ShelfLifeStatusService::statusForProducts(
                    $orgId,
                    [$productId],
                    [$productId => $destStock],
                    24,
                    [$productId => $warehouseFresh]
                );
                $expired = (float) ($shelf[$productId]['expiredQty'] ?? 0);
            }

            $product = $meta->get($productId);
            $products[] = [
                'productId' => $productId,
                'name' => $product?->name ?: 'Unknown',
                'unit' => $product?->unit,
                'expiredQty' => $expired,
                'currentStock' => $destStock,
            ];
        }

        return [
            'branchId' => (string) $destination->id,
            'branchName' => (string) ($destination->name ?: 'Retail branch'),
            'products' => $products,
        ];
    }
}
