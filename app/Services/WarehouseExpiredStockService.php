<?php

namespace App\Services;

use App\Modules\Api\V1\Branch\Models\Branch;
use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\ProductionBatch\Models\ProductionBatch;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WarehouseExpiredStockService
{
    /** @var array<string, array{expiredQty: float, currentStock: float}> */
    private static array $warehouseShelfCache = [];

    public static function warehouseBranchForOrg(string $orgId): ?Branch
    {
        return Branch::query()
            ->where('organization_id', $orgId)
            ->whereRaw('LOWER(type) = ?', ['warehouse'])
            ->orderBy('created_at')
            ->first();
    }

    public static function warehouseNameForOrg(string $orgId): string
    {
        return self::warehouseBranchForOrg($orgId)?->name ?: 'Main Warehouse';
    }

    public static function forgetCache(): void
    {
        self::$warehouseShelfCache = [];
    }

    /**
     * Completed production still counted as warehouse lots (cancelled / disposed excluded).
     */
    public static function openOwnProductionQty(string $orgId, string $productId): float
    {
        return round((float) ProductionBatch::query()
            ->where('organization_id', $orgId)
            ->where('product_id', $productId)
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw('LOWER(status) NOT IN (?, ?, ?)', ['wasted', 'cancelled', 'disposed']);
            })
            ->sum('quantity_produced'), 2);
    }

    /**
     * Warehouse on-hand for an own product cannot exceed open (non-cancelled, non-disposed) batches.
     * Ghost leftover from old wasted rows that never deducted the ledger is dropped.
     */
    public static function warehouseOnHand(string $orgId, string $productId, bool $persist = false): float
    {
        /** @var Product|null $product */
        $product = Product::query()
            ->where('organization_id', $orgId)
            ->where('id', $productId)
            ->first();
        if (! $product) {
            return 0.0;
        }

        $ledger = max(0.0, round((float) $product->current_stock, 2));
        if ($product->isBought()) {
            return $ledger;
        }

        $onHand = round(min($ledger, self::openOwnProductionQty($orgId, $productId)), 2);
        if ($persist && $ledger > $onHand + 0.001) {
            $product->current_stock = $onHand;
            $product->save();
            self::forgetCache();
        }

        return $onHand;
    }

    /**
     * Warehouse FG expired qty for a product (ledger gate = sellable warehouse on-hand).
     *
     * @return array{expiredQty: float, currentStock: float, hasFreshLot: bool, shelfStatus: ?string}
     */
    public static function warehouseShelfForProduct(string $orgId, string $productId): array
    {
        $key = $orgId.':'.$productId;
        if (! isset(self::$warehouseShelfCache[$key])) {
            $onHand = self::warehouseOnHand($orgId, $productId, true);

            $shelf = ShelfLifeStatusService::statusForProducts(
                $orgId,
                [$productId],
                [$productId => $onHand]
            );
            $info = $shelf[$productId] ?? [];
            self::$warehouseShelfCache[$key] = [
                'expiredQty' => (float) ($info['expiredQty'] ?? 0),
                'currentStock' => $onHand,
                'hasFreshLot' => (bool) ($info['hasFreshLot'] ?? false),
                'shelfStatus' => $info['shelfStatus'] ?? null,
            ];
        }

        return self::$warehouseShelfCache[$key];
    }

    /**
     * Inferred location + allowed actions for a production batch.
     *
     * @return array{
     *   currentLocation: string,
     *   locationType: string,
     *   canProcessExpiredStock: bool,
     *   canReturnExpiredStock: bool,
     *   expiredAtBranchId: ?string,
     *   expiredAtBranchName: ?string,
     *   expiredAtBranchQty: float,
     *   warehouseExpiredQty: float,
     *   warehouseName: string
     * }
     */
    public static function locationContext(ProductionBatch $batch, bool $includeRetailExpiry = true): array
    {
        $orgId = (string) $batch->organization_id;
        $productId = (string) $batch->product_id;
        $warehouseName = self::warehouseNameForOrg($orgId);
        $status = strtolower((string) ($batch->status ?? ''));
        $shelf = ShelfLifeStatusService::statusForTimestamp($batch->expiry_timestamp);
        $isExpired = ($shelf['shelfStatus'] ?? null) === ShelfLifeStatusService::STATUS_EXPIRED;
        $warehouse = self::warehouseShelfForProduct($orgId, $productId);
        $warehouseExpiredQty = (float) $warehouse['expiredQty'];
        $warehouseStock = (float) $warehouse['currentStock'];

        $base = [
            'currentLocation' => $warehouseName,
            'locationType' => 'warehouse',
            'canProcessExpiredStock' => false,
            'canReturnExpiredStock' => false,
            'expiredAtBranchId' => null,
            'expiredAtBranchName' => null,
            'expiredAtBranchQty' => 0.0,
            'warehouseExpiredQty' => $warehouseExpiredQty,
            'warehouseName' => $warehouseName,
        ];

        if (in_array($status, ['wasted', 'disposed'], true)) {
            $base['currentLocation'] = 'Disposed';
            $base['locationType'] = 'disposed';

            return $base;
        }

        if ($status === 'cancelled') {
            $base['currentLocation'] = 'Cancelled';
            $base['locationType'] = 'cancelled';

            return $base;
        }

        if ($isExpired && $warehouseExpiredQty > 0.001) {
            $base['canProcessExpiredStock'] = true;
            $base['currentLocation'] = $warehouseName;
            $base['locationType'] = 'warehouse';

            return $base;
        }

        $retail = $includeRetailExpiry
            ? self::firstRetailExpiredStock($orgId, $productId)
            : null;

        if ($isExpired && $retail) {
            $base['currentLocation'] = $retail['branchName'];
            $base['locationType'] = 'retail';
            $base['canReturnExpiredStock'] = true;
            $base['expiredAtBranchId'] = $retail['branchId'];
            $base['expiredAtBranchName'] = $retail['branchName'];
            $base['expiredAtBranchQty'] = $retail['expiredQty'];

            return $base;
        }

        if ($warehouseStock > 0.001) {
            $base['currentLocation'] = $warehouseName;
            $base['locationType'] = 'warehouse';

            return $base;
        }

        $base['currentLocation'] = 'Transferred';
        $base['locationType'] = 'transferred';

        return $base;
    }

    /**
     * @return array{branchId: string, branchName: string, expiredQty: float}|null
     */
    public static function firstRetailExpiredStock(string $orgId, string $productId): ?array
    {
        $rows = BranchStock::query()
            ->with('branch')
            ->where('organization_id', $orgId)
            ->where('product_id', $productId)
            ->where('current_stock', '>', 0)
            ->get();

        foreach ($rows as $row) {
            $branch = $row->branch;
            if (! $branch || strtolower((string) ($branch->type ?? '')) === 'warehouse') {
                continue;
            }
            $stock = (float) $row->current_stock;
            $warehouse = self::warehouseShelfForProduct($orgId, $productId);
            $warehouseFresh = max(0.0, round((float) $warehouse['currentStock'] - (float) $warehouse['expiredQty'], 2));
            $shelf = ShelfLifeStatusService::statusForProducts(
                $orgId,
                [$productId],
                [$productId => $stock],
                24,
                [$productId => $warehouseFresh]
            );
            $expiredQty = (float) ($shelf[$productId]['expiredQty'] ?? 0);
            if ($expiredQty > 0.001) {
                return [
                    'branchId' => (string) $row->branch_id,
                    'branchName' => (string) ($branch->name ?: 'Retail branch'),
                    'expiredQty' => $expiredQty,
                ];
            }
        }

        return null;
    }

    public static function oldestExpiredWarehouseBatch(string $orgId, string $productId): ?ProductionBatch
    {
        $warehouse = self::warehouseShelfForProduct($orgId, $productId);
        if ((float) $warehouse['expiredQty'] <= 0.001) {
            return null;
        }

        $now = Carbon::now();

        return ProductionBatch::query()
            ->where('organization_id', $orgId)
            ->where('product_id', $productId)
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw('LOWER(status) NOT IN (?, ?, ?)', ['wasted', 'cancelled', 'disposed']);
            })
            ->whereNotNull('expiry_timestamp')
            ->where('expiry_timestamp', '<', $now)
            ->orderBy('expiry_timestamp')
            ->first();
    }

    /**
     * Dispose expired warehouse leftover for a specific production batch.
     * Deducts products.current_stock. Does not create a SalesReturn.
     *
     * @return array{quantity: float, warehouseName: string, batchNumber: string, productName: string}
     */
    public function dispose(ProductionBatch $batch, string $orgId): array
    {
        return DB::transaction(function () use ($batch, $orgId) {
            /** @var ProductionBatch $locked */
            $locked = ProductionBatch::query()
                ->where('organization_id', $orgId)
                ->where('id', $batch->id)
                ->lockForUpdate()
                ->firstOrFail();

            $status = strtolower((string) ($locked->status ?? ''));
            if (in_array($status, ['wasted', 'disposed'], true)) {
                throw new \RuntimeException('This production batch has already been disposed.');
            }
            if ($status === 'cancelled') {
                throw new \RuntimeException('Cancelled production batches cannot be disposed.');
            }

            $shelf = ShelfLifeStatusService::statusForTimestamp($locked->expiry_timestamp);
            if (($shelf['shelfStatus'] ?? null) !== ShelfLifeStatusService::STATUS_EXPIRED) {
                throw new \RuntimeException('Only expired production batches can be disposed.');
            }

            $product = Product::query()
                ->where('organization_id', $orgId)
                ->where('id', $locked->product_id)
                ->lockForUpdate()
                ->firstOrFail();

            self::forgetCache();
            $warehouseName = self::warehouseNameForOrg($orgId);
            $ledger = (float) $product->current_stock;
            $shelfInfo = ShelfLifeStatusService::statusForProducts(
                $orgId,
                [(string) $product->id],
                [(string) $product->id => $ledger]
            );
            $warehouseExpiredQty = (float) ($shelfInfo[(string) $product->id]['expiredQty'] ?? 0);
            $batchQty = (float) ($locked->quantity_produced ?? 0);
            $disposeQty = round(min($batchQty, $warehouseExpiredQty, $ledger), 2);

            $productName = (string) ($product->name ?: 'This product');
            $batchNumber = (string) ($locked->batch_number ?: $locked->id);

            if ($disposeQty <= 0.001) {
                throw new \RuntimeException(
                    "{$productName} — Batch {$batchNumber} is not expired stock in {$warehouseName}."
                );
            }

            $product->current_stock = round($ledger - $disposeQty, 2);
            $product->save();

            $locked->status = 'wasted';
            $locked->wasted_at = Carbon::now();
            $locked->wasted_quantity = $disposeQty;
            $locked->wasted_reason = 'Expired';
            $locked->save();

            self::forgetCache();

            return [
                'quantity' => $disposeQty,
                'warehouseName' => $warehouseName,
                'batchNumber' => $batchNumber,
                'productName' => $productName,
                'unit' => (string) ($product->unit ?: 'pcs'),
            ];
        });
    }
}
