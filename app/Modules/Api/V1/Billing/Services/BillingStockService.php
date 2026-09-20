<?php

namespace App\Modules\Api\V1\Billing\Services;

use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use App\Services\ShelfLifeStatusService;

class BillingStockService
{
    /**
     * Apply signed stock deltas to branch stock.
     * Positive delta = add back to stock; negative = deduct.
     *
     * When $requireFresh is true, deductions are gated to ledger minus expiredQty
     * after locking the BranchStock rows (same lock as the write).
     *
     * @param  array<string, float>  $deltas  productId => signed quantity
     */
    public function applyDeltas(string $orgId, string $branchId, array $deltas, bool $requireFresh = false): void
    {
        $work = [];
        foreach ($deltas as $productId => $delta) {
            $productId = (string) $productId;
            $delta = (float) $delta;
            if ($delta == 0.0 || $productId === '') {
                continue;
            }
            $work[$productId] = $delta;
        }
        if ($work === []) {
            return;
        }

        ksort($work);

        $locked = [];
        foreach ($work as $productId => $delta) {
            $branchStock = BranchStock::where('organization_id', $orgId)
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();
            $locked[$productId] = ['row' => $branchStock, 'delta' => $delta];
        }

        if ($requireFresh) {
            $needed = [];
            $stockByProduct = [];
            foreach ($locked as $productId => $item) {
                if ($item['delta'] >= 0.0) {
                    continue;
                }
                $needed[$productId] = abs((float) $item['delta']);
                $stockByProduct[$productId] = $item['row'] ? (float) $item['row']->current_stock : 0.0;
            }
            $this->assertFreshAgainstLocked($orgId, $needed, $stockByProduct);
        }

        foreach ($locked as $productId => $item) {
            $delta = (float) $item['delta'];
            $branchStock = $item['row'];

            if ($delta < 0) {
                $needed = abs($delta);
                if (! $branchStock || (float) $branchStock->current_stock < $needed) {
                    $available = $branchStock ? (float) $branchStock->current_stock : 0;
                    throw new \RuntimeException(
                        "Insufficient branch stock for product {$productId}. Needed: {$needed}, Available: {$available}"
                    );
                }
                $branchStock->current_stock = (float) $branchStock->current_stock - $needed;
                $branchStock->save();
                continue;
            }

            if (! $branchStock) {
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
     * Deduct sold quantities from branch stock (paid bill).
     * Caps to fresh on-hand (ledger − expiredQty) — expired stock is not sellable.
     *
     * @param  array<int, array{productId:string, quantity:float|int|string}>  $items
     */
    public function deductForSale(string $orgId, string $branchId, array $items): void
    {
        $this->applyDeltas($orgId, $branchId, $this->negativeDeltasFromItems($items), true);
    }

    /**
     * Deduct wastage / sales-return quantities from branch ledger stock.
     * Allows writing off expired (and fresh) on-hand — no fresh-only gate.
     * Still rejects qty above total branch current_stock.
     *
     * @param  array<int, array{productId:string, quantity:float|int|string}>  $items
     */
    public function deductForWastage(string $orgId, string $branchId, array $items): void
    {
        $this->applyDeltas($orgId, $branchId, $this->negativeDeltasFromItems($items), false);
    }

    /**
     * @param  array<int, array{productId:string, quantity:float|int|string}>  $items
     * @return array<string, float>
     */
    private function negativeDeltasFromItems(array $items): array
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

        return $deltas;
    }

    /**
     * Restore quantities to branch stock (cancel paid bill).
     *
     * @param  array<int, array{productId:string, quantity:float|int|string}>  $items
     */
    public function restoreForSale(string $orgId, string $branchId, array $items): void
    {
        $deltas = [];
        foreach ($items as $item) {
            $productId = (string) ($item['productId'] ?? '');
            $qty = (float) ($item['quantity'] ?? 0);
            if ($productId === '' || $qty <= 0) {
                continue;
            }
            $deltas[$productId] = ($deltas[$productId] ?? 0) + $qty;
        }

        $this->applyDeltas($orgId, $branchId, $deltas, false);
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
            $this->applyDeltas($orgId, $newBranchId, $deltas, true);
            return;
        }

        // Different branch: restore full old qty to old branch, deduct new from new branch
        $restore = [];
        foreach ($oldQty as $productId => $qty) {
            $restore[$productId] = $qty;
        }
        $this->applyDeltas($orgId, $oldBranchId, $restore, false);

        $deduct = [];
        foreach ($newQty as $productId => $qty) {
            $deduct[$productId] = -$qty;
        }
        $this->applyDeltas($orgId, $newBranchId, $deduct, true);
    }

    /**
     * POS may only sell fresh on-hand (ledger minus expiredQty).
     * Callers must pass stock values already read under lockForUpdate.
     *
     * @param  array<string, float>  $needed  productId => qty to deduct
     * @param  array<string, float>  $stockByProduct  locked ledger
     */
    private function assertFreshAgainstLocked(string $orgId, array $needed, array $stockByProduct): void
    {
        if ($needed === []) {
            return;
        }

        $shelfMap = ShelfLifeStatusService::statusForProducts($orgId, array_keys($needed), $stockByProduct);

        foreach ($needed as $productId => $qty) {
            $ledger = (float) ($stockByProduct[$productId] ?? 0);
            $expired = (float) ($shelfMap[$productId]['expiredQty'] ?? 0);
            $fresh = max(0.0, round($ledger - $expired, 2));
            if ($qty > $fresh + 0.001) {
                throw new \RuntimeException(
                    "Insufficient fresh stock for product {$productId}. Needed: {$qty}, Available: {$fresh}"
                );
            }
        }
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
