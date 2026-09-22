<?php

namespace App\Services;

use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\ProductionBatch\Models\ProductionBatch;
use App\Modules\Api\V1\ProductStockTransaction\Models\ProductStockTransaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ShelfLifeStatusService
{
    public const STATUS_EXPIRED = 'expired';

    public const STATUS_EXPIRING = 'expiring';

    public const STATUS_FRESH = 'fresh';

    /**
     * Map productId => shelf info for the given products.
     *
     * FIFO: leftover on-hand after attributing fresh lots is expiredQty.
     * shelfStatus is expired only when expiredQty > 0. hasFreshLot is remaining
     * on-hand after expiredQty (stock - expiredQty > 0).
     *
     * @param  array<int, string>  $productIds
     * @param  array<string, float>  $stockByProduct  productId => currentStock
     * @param  array<string, float>  $excludeFreshQtyByProduct  fresh qty known to sit elsewhere (e.g. warehouse)
     * @return array<string, array{
     *   shelfStatus: ?string,
     *   earliestExpiry: ?string,
     *   expiredQty: float,
     *   hasFreshLot: bool
     * }>
     */
    public static function statusForProducts(
        string $orgId,
        array $productIds,
        array $stockByProduct = [],
        int $warningHours = 24,
        array $excludeFreshQtyByProduct = []
    ): array {
        $productIds = array_values(array_unique(array_filter($productIds)));
        if ($productIds === []) {
            return [];
        }

        $now = Carbon::now();
        $warningThreshold = $now->copy()->addHours($warningHours);

        /** @var Collection<string, Collection<int, ProductionBatch>> $byProduct */
        $byProduct = ProductionBatch::query()
            ->where('organization_id', $orgId)
            ->whereIn('product_id', $productIds)
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw('LOWER(status) NOT IN (?, ?, ?)', ['wasted', 'cancelled', 'disposed']);
            })
            ->whereNotNull('expiry_timestamp')
            ->orderBy('expiry_timestamp')
            ->get(['product_id', 'expiry_timestamp', 'quantity_produced'])
            ->groupBy('product_id');

        $useStockGate = $stockByProduct !== [];
        $result = [];

        $receiptQuery = ProductStockTransaction::query()
            ->where('organization_id', $orgId)
            ->whereIn('product_id', $productIds)
            ->where('type', 'in')
            ->whereNotNull('expiry_date')
            ->orderBy('expiry_date');

        if (\Illuminate\Support\Facades\Schema::hasColumn('product_stock_transactions', 'wasted_at')) {
            $receiptQuery->whereNull('wasted_at');
        }

        /** @var Collection<string, Collection<int, ProductStockTransaction>> $receiptsByProduct */
        $receiptsByProduct = $receiptQuery
            ->get(['product_id', 'expiry_date', 'quantity'])
            ->groupBy('product_id');

        /** @var Collection<string, Product> $productsById */
        $productsById = Product::query()
            ->where('organization_id', $orgId)
            ->whereIn('id', $productIds)
            ->get(['id', 'product_source', 'expiry_date', 'current_stock'])
            ->keyBy(fn ($p) => (string) $p->id);

        foreach ($productIds as $productId) {
            $productId = (string) $productId;
            $stock = $useStockGate
                ? (float) ($stockByProduct[$productId] ?? 0)
                : (float) ($productsById->get($productId)?->current_stock ?? 1.0);

            if ($useStockGate && $stock <= 0) {
                $result[$productId] = [
                    'shelfStatus' => null,
                    'earliestExpiry' => null,
                    'expiredQty' => 0.0,
                    'hasFreshLot' => false,
                ];
                continue;
            }

            $pastLots = [];
            $futureLots = [];

            $batches = $byProduct->get($productId);
            if ($batches) {
                foreach ($batches as $batch) {
                    $expiry = $batch->expiry_timestamp;
                    if (! $expiry) {
                        continue;
                    }
                    $qty = (float) ($batch->quantity_produced ?? 0);
                    $lot = ['expiry' => $expiry->copy(), 'qty' => max(0.0, $qty)];
                    if ($expiry->isPast()) {
                        $pastLots[] = $lot;
                    } else {
                        $futureLots[] = $lot;
                    }
                }
            }

            $receipts = $receiptsByProduct->get($productId);
            if ($receipts) {
                foreach ($receipts as $receipt) {
                    if (! $receipt->expiry_date) {
                        continue;
                    }
                    $expiry = Carbon::parse($receipt->expiry_date)->endOfDay();
                    $qty = (float) ($receipt->quantity ?? 0);
                    $lot = ['expiry' => $expiry, 'qty' => max(0.0, $qty)];
                    if ($expiry->isPast()) {
                        $pastLots[] = $lot;
                    } else {
                        $futureLots[] = $lot;
                    }
                }
            }

            // Bought product-level pack expiry (when no receipt lots cover it)
            $product = $productsById->get($productId);
            if ($product && $product->isBought() && $product->expiry_date) {
                $expiry = Carbon::parse($product->expiry_date)->endOfDay();
                $hasReceiptLots = ($receipts && $receipts->count() > 0);
                if (! $hasReceiptLots) {
                    // Prefer branch/on-hand gate stock when provided (POS / branch list).
                    $qty = max(0.0, (float) $stock);
                    $lot = ['expiry' => $expiry, 'qty' => $qty > 0 ? $qty : 1.0];
                    if ($expiry->isPast()) {
                        $pastLots[] = $lot;
                    } else {
                        $futureLots[] = $lot;
                    }
                } elseif ($expiry->isPast()) {
                    // Product date past but receipts exist — still count as past signal with 0 extra qty
                    // if receipts already contribute; only add if no past receipt yet
                    $hasPastReceipt = collect($pastLots)->isNotEmpty();
                    if (! $hasPastReceipt) {
                        $pastLots[] = ['expiry' => $expiry, 'qty' => max(0.0, (float) $stock)];
                    }
                }
            }

            $pastSum = round(array_sum(array_column($pastLots, 'qty')), 2);
            $freshSum = round(array_sum(array_column($futureLots, 'qty')), 2);
            $excludeFresh = max(0.0, (float) ($excludeFreshQtyByProduct[$productId] ?? 0));
            if ($excludeFresh > 0) {
                $freshSum = round(max(0.0, $freshSum - $excludeFresh), 2);
            }
            // FIFO remaining: treat freshest produced as still on hand first, then attribute
            // leftover stock to expired lots. Caps prevent fresh receipts from inflating Expired · N.
            if ($stock <= 0) {
                $expiredQty = 0.0;
            } else {
                $freshOnHand = min($freshSum, $stock);
                $expiredQty = round(min($pastSum, max(0.0, $stock - $freshOnHand)), 2);
            }
            $hasFutureLots = count($futureLots) > 0;
            $hasPastLot = count($pastLots) > 0;
            $hasFreshLot = round(max(0.0, $stock - $expiredQty), 2) > 0;

            $earliestPast = null;
            foreach ($pastLots as $lot) {
                if ($earliestPast === null || $lot['expiry']->lt($earliestPast)) {
                    $earliestPast = $lot['expiry']->copy();
                }
            }
            $earliestFuture = null;
            $hasExpiring = false;
            foreach ($futureLots as $lot) {
                if ($earliestFuture === null || $lot['expiry']->lt($earliestFuture)) {
                    $earliestFuture = $lot['expiry']->copy();
                }
                if ($lot['expiry']->lessThanOrEqualTo($warningThreshold)) {
                    $hasExpiring = true;
                }
            }

            if (! $hasPastLot && ! $hasFutureLots) {
                $result[$productId] = [
                    'shelfStatus' => self::STATUS_FRESH,
                    'earliestExpiry' => null,
                    'expiredQty' => 0.0,
                    'hasFreshLot' => $hasFreshLot,
                ];
                continue;
            }

            // Expired only when on-hand leftover is attributed to past lots.
            if ($expiredQty > 0) {
                $result[$productId] = [
                    'shelfStatus' => self::STATUS_EXPIRED,
                    'earliestExpiry' => $earliestPast?->format('Y-m-d H:i:s'),
                    'expiredQty' => $expiredQty,
                    'hasFreshLot' => $hasFreshLot,
                ];
                continue;
            }

            $status = $hasExpiring ? self::STATUS_EXPIRING : self::STATUS_FRESH;
            $result[$productId] = [
                'shelfStatus' => $status,
                'earliestExpiry' => $earliestFuture?->format('Y-m-d H:i:s'),
                'expiredQty' => 0.0,
                'hasFreshLot' => $hasFreshLot,
            ];
        }

        return $result;
    }

    /**
     * Classify a single expiry timestamp (production batch row).
     *
     * @return array{shelfStatus: ?string, earliestExpiry: ?string}
     */
    public static function statusForTimestamp(?Carbon $expiry, int $warningHours = 24): array
    {
        if (! $expiry) {
            return [
                'shelfStatus' => null,
                'earliestExpiry' => null,
            ];
        }

        $now = Carbon::now();
        $warningThreshold = $now->copy()->addHours($warningHours);

        if ($expiry->isPast()) {
            $status = self::STATUS_EXPIRED;
        } elseif ($expiry->lessThanOrEqualTo($warningThreshold)) {
            $status = self::STATUS_EXPIRING;
        } else {
            $status = self::STATUS_FRESH;
        }

        return [
            'shelfStatus' => $status,
            'earliestExpiry' => $expiry->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Mark oldest past-expiry lots as wasted covering $qty (FIFO by expiry).
     * Own: production_batches.status = wasted.
     * Bought: product_stock_transactions.wasted_at = now().
     */
    public static function markExpiredLotsWasted(string $orgId, string $productId, float $qty): float
    {
        $remaining = max(0.0, $qty);
        if ($remaining <= 0) {
            return 0.0;
        }

        $now = Carbon::now();
        $marked = 0.0;

        $batches = ProductionBatch::query()
            ->where('organization_id', $orgId)
            ->where('product_id', $productId)
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw('LOWER(status) NOT IN (?, ?, ?)', ['wasted', 'cancelled', 'disposed']);
            })
            ->whereNotNull('expiry_timestamp')
            ->where('expiry_timestamp', '<', $now)
            ->orderBy('expiry_timestamp')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $batchQty = (float) ($batch->quantity_produced ?? 0);
            if ($batchQty <= 0) {
                $batch->status = 'wasted';
                $batch->save();
                continue;
            }
            // Whole-batch mark (no partial batch qty column) when return covers any of this lot
            $batch->status = 'wasted';
            $batch->save();
            $take = min($remaining, $batchQty);
            $remaining -= $take;
            $marked += $take;
        }

        $receiptQuery = ProductStockTransaction::query()
            ->where('organization_id', $orgId)
            ->where('product_id', $productId)
            ->where('type', 'in')
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', $now->toDateString())
            ->orderBy('expiry_date');

        if (\Illuminate\Support\Facades\Schema::hasColumn('product_stock_transactions', 'wasted_at')) {
            $receiptQuery->whereNull('wasted_at');
        }

        $receipts = $receiptQuery->lockForUpdate()->get();
        foreach ($receipts as $receipt) {
            if ($remaining <= 0) {
                break;
            }
            if (! \Illuminate\Support\Facades\Schema::hasColumn('product_stock_transactions', 'wasted_at')) {
                break;
            }
            $receiptQty = (float) ($receipt->quantity ?? 0);
            $receipt->wasted_at = $now;
            $receipt->save();
            $take = min($remaining, max(0.01, $receiptQty));
            $remaining -= $take;
            $marked += $take;
        }

        // Bought product-level expiry: clear when past and return covered remaining
        $product = Product::query()
            ->where('organization_id', $orgId)
            ->where('id', $productId)
            ->lockForUpdate()
            ->first();
        if ($product && $product->isBought() && $product->expiry_date) {
            $expiry = Carbon::parse($product->expiry_date)->endOfDay();
            if ($expiry->isPast() && $marked >= $qty - 0.001) {
                // Keep date for history; status service ignores when no open past receipts
                // and stock gate / fresh receipts drive Fresh. If still past with stock and
                // no receipts, clear product expiry after full waste of expired stock.
                if ($remaining <= 0.001) {
                    $product->expiry_date = null;
                    $product->save();
                }
            }
        }

        return $marked;
    }

    /**
     * Products with stock at a branch, including shelf status (for dashboard / toast).
     *
     * @return array{
     *   summary: array{expiredCount: int, expiringSoonCount: int, freshCount: int},
     *   products: list<array{
     *     productId: string,
     *     name: string,
     *     productNumber: mixed,
     *     currentStock: float,
     *     shelfStatus: string,
     *     earliestExpiry: ?string,
     *     expiredQty: float,
     *     hasFreshLot: bool
     *   }>
     * }
     */
    public static function forBranch(string $orgId, string $branchId, int $warningHours = 24): array
    {
        $stocks = BranchStock::query()
            ->where('organization_id', $orgId)
            ->where('branch_id', $branchId)
            ->where('current_stock', '>', 0)
            ->get(['product_id', 'current_stock']);

        if ($stocks->isEmpty()) {
            return [
                'summary' => [
                    'expiredCount' => 0,
                    'expiringSoonCount' => 0,
                    'freshCount' => 0,
                ],
                'products' => [],
            ];
        }

        $stockByProduct = $stocks
            ->mapWithKeys(fn ($row) => [(string) $row->product_id => (float) $row->current_stock])
            ->all();

        $productIds = array_keys($stockByProduct);
        $statusMap = self::statusForProducts($orgId, $productIds, $stockByProduct, $warningHours);

        $productsMeta = Product::query()
            ->where('organization_id', $orgId)
            ->whereIn('id', $productIds)
            ->get(['id', 'name', 'product_number', 'unit'])
            ->keyBy('id');

        $products = [];
        $expiredCount = 0;
        $expiringCount = 0;
        $freshCount = 0;

        foreach ($productIds as $productId) {
            $info = $statusMap[$productId] ?? null;
            $status = $info['shelfStatus'] ?? self::STATUS_FRESH;
            if ($status === null) {
                continue;
            }

            if ($status === self::STATUS_EXPIRED) {
                $expiredCount++;
            } elseif ($status === self::STATUS_EXPIRING) {
                $expiringCount++;
            } else {
                $freshCount++;
            }

            if ($status === self::STATUS_FRESH) {
                continue;
            }

            $meta = $productsMeta->get($productId);
            $products[] = [
                'productId' => $productId,
                'name' => $meta?->name ?? 'Unknown',
                'productNumber' => $meta?->product_number,
                'unit' => $meta?->unit,
                'currentStock' => (float) ($stockByProduct[$productId] ?? 0),
                'shelfStatus' => $status,
                'earliestExpiry' => $info['earliestExpiry'] ?? null,
                'expiredQty' => (float) ($info['expiredQty'] ?? 0),
                'hasFreshLot' => (bool) ($info['hasFreshLot'] ?? false),
            ];
        }

        usort($products, function ($a, $b) {
            $rank = [self::STATUS_EXPIRED => 0, self::STATUS_EXPIRING => 1];
            $ra = $rank[$a['shelfStatus']] ?? 9;
            $rb = $rank[$b['shelfStatus']] ?? 9;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }

            return strcmp((string) ($a['earliestExpiry'] ?? ''), (string) ($b['earliestExpiry'] ?? ''));
        });

        return [
            'summary' => [
                'expiredCount' => $expiredCount,
                'expiringSoonCount' => $expiringCount,
                'freshCount' => $freshCount,
            ],
            'products' => $products,
        ];
    }
}
