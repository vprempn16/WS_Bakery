<?php

namespace App\Modules\Api\V1\Billing\Services;

use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use Illuminate\Support\Facades\DB;

class BillingStockService
{
    /**
     * Apply signed stock deltas to branch stock.
     * Positive delta = add back to stock; negative = deduct.
     *
     * @param  array<string, float>  $deltas  productId => signed quantity
     */
    public function applyDeltas(string $orgId, string $branchId, array $deltas): void
    {
        foreach ($deltas as $productId => $delta) {
            $delta = (float) $delta;
            if ($delta == 0.0 || $productId === '') {
                continue;
            }

            $branchStock = BranchStock::where('organization_id', $orgId)
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if ($delta < 0) {
                $needed = abs($delta);
                if (!$branchStock || (float) $branchStock->current_stock < $needed) {
                    $available = $branchStock ? (float) $branchStock->current_stock : 0;
                    throw new \RuntimeException(
                        "Insufficient branch stock for product {$productId}. Needed: {$needed}, Available: {$available}"
                    );
                }
                $branchStock->current_stock = (float) $branchStock->current_stock - $needed;
                $branchStock->save();
                continue;
            }

            if (!$branchStock) {
                $branchStock = BranchStock::create([
                    'organization_id' => $orgId,
                    'branch_id' => $branchId,
                    'product_id' => $productId,
                    'current_stock' => 0,
                ]);
            }
            $branchStock->current_stock = (float) $branchStock->current_stock + $delta;
            $branchStock->save();
        }
    }

    /**
     * Deduct sold quantities from branch stock (create bill).
     *
     * @param  array<int, array{productId:string, quantity:float|int|string}>  $items
     */
    public function deductForSale(string $orgId, string $branchId, array $items): void
    {
        $deltas = [];
        foreach ($items as $item) {
            $productId = (string) ($item['productId'] ?? '');
            $qty = (float) ($item['quantity'] ?? 0);
            if ($productId === '' || $qty <= 0) {
                continue;
            }
            $deltas[$productId] = ($deltas[$productId] ?? 0) - $qty;
        }

        $this->applyDeltas($orgId, $branchId, $deltas);
    }

    /**
     * Reconcile stock when bill items (or branch) change.
     *
     * @param  array<int, array{product_id?:string, productId?:string, quantity:float|int|string}>  $oldItems
     * @param  array<int, array{productId:string, quantity:float|int|string}>  $newItems
     */
    public function reconcileSale(
        string $orgId,
        string $oldBranchId,
        string $newBranchId,
        array $oldItems,
        array $newItems
    ): void {
        $oldQty = $this->qtyByProduct($oldItems);
        $newQty = $this->qtyByProduct($newItems);

        if ($oldBranchId === $newBranchId) {
            $deltas = [];
            foreach (array_unique(array_merge(array_keys($oldQty), array_keys($newQty))) as $productId) {
                // Restore old, then deduct new → delta = old - new
                $deltas[$productId] = ($oldQty[$productId] ?? 0) - ($newQty[$productId] ?? 0);
            }
            $this->applyDeltas($orgId, $newBranchId, $deltas);
            return;
        }

        // Different branch: restore full old qty to old branch, deduct new from new branch
        $restore = [];
        foreach ($oldQty as $productId => $qty) {
            $restore[$productId] = $qty;
        }
        $this->applyDeltas($orgId, $oldBranchId, $restore);

        $deduct = [];
        foreach ($newQty as $productId => $qty) {
            $deduct[$productId] = -$qty;
        }
        $this->applyDeltas($orgId, $newBranchId, $deduct);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, float>
     */
    private function qtyByProduct(array $items): array
    {
        $map = [];
        foreach ($items as $item) {
            $productId = (string) ($item['productId'] ?? $item['product_id'] ?? '');
            $qty = (float) ($item['quantity'] ?? 0);
            if ($productId === '' || $qty <= 0) {
                continue;
            }
            $map[$productId] = ($map[$productId] ?? 0) + $qty;
        }

        return $map;
    }
}
