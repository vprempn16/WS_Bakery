<?php

namespace App\Modules\Api\V1\BranchTransfer\Services;

use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use App\Modules\Api\V1\BranchTransfer\Models\BranchTransfer;
use App\Modules\Api\V1\Product\Models\Product;

class BranchTransferStockService
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

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

            if ((float) $product->current_stock < $quantity) {
                throw new \RuntimeException(
                    "Insufficient warehouse stock for {$product->name}. Available: {$product->current_stock}, requested: {$quantity}."
                );
            }
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

            if ((float) $product->current_stock < $qty) {
                throw new \RuntimeException(
                    "Insufficient warehouse stock for {$product->name}. Available: {$product->current_stock}, requested: {$qty}."
                );
            }

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
}
