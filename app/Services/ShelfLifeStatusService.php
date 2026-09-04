<?php

namespace App\Services;

use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\ProductionBatch\Models\ProductionBatch;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ShelfLifeStatusService
{
    public const STATUS_EXPIRED = 'expired';

    public const STATUS_EXPIRING = 'expiring';

    public const STATUS_FRESH = 'fresh';

    /**
     * Map productId => { shelfStatus, earliestExpiry } for the given products.
     *
     * Heuristic (no FIFO batch↔branch link): uses any non-wasted ProductionBatch
     * expiry for the product. When $stockByProduct is provided and qty <= 0,
     * shelfStatus is null (no warning badge).
     *
     * @param  array<int, string>  $productIds
     * @param  array<string, float>  $stockByProduct  productId => currentStock
     * @return array<string, array{shelfStatus: ?string, earliestExpiry: ?string}>
     */
    public static function statusForProducts(
        string $orgId,
        array $productIds,
        array $stockByProduct = [],
        int $warningHours = 24
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
                $q->whereNull('status')->orWhere('status', '!=', 'wasted');
            })
            ->whereNotNull('expiry_timestamp')
            ->orderBy('expiry_timestamp')
            ->get(['product_id', 'expiry_timestamp'])
            ->groupBy('product_id');

        $useStockGate = $stockByProduct !== [];
        $result = [];

        foreach ($productIds as $productId) {
            $productId = (string) $productId;
            $stock = $useStockGate ? (float) ($stockByProduct[$productId] ?? 0) : 1.0;

            if ($useStockGate && $stock <= 0) {
                $result[$productId] = [
                    'shelfStatus' => null,
                    'earliestExpiry' => null,
                ];
                continue;
            }

            $batches = $byProduct->get($productId);
            if (! $batches || $batches->isEmpty()) {
                $result[$productId] = [
                    'shelfStatus' => self::STATUS_FRESH,
                    'earliestExpiry' => null,
                ];
                continue;
            }

            $earliest = null;
            $hasExpired = false;
            $hasExpiring = false;

            foreach ($batches as $batch) {
                $expiry = $batch->expiry_timestamp;
                if (! $expiry) {
                    continue;
                }
                if ($earliest === null || $expiry->lt($earliest)) {
                    $earliest = $expiry->copy();
                }
                if ($expiry->isPast()) {
                    $hasExpired = true;
                } elseif ($expiry->lessThanOrEqualTo($warningThreshold)) {
                    $hasExpiring = true;
                }
            }

            $status = self::STATUS_FRESH;
            if ($hasExpired) {
                $status = self::STATUS_EXPIRED;
            } elseif ($hasExpiring) {
                $status = self::STATUS_EXPIRING;
            }

            $result[$productId] = [
                'shelfStatus' => $status,
                'earliestExpiry' => $earliest ? $earliest->format('Y-m-d H:i:s') : null,
            ];
        }

        return $result;
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
     *     earliestExpiry: ?string
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
            ->get(['id', 'name', 'product_number'])
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

            // Dashboard / toast list focuses on warning products
            if ($status === self::STATUS_FRESH) {
                continue;
            }

            $meta = $productsMeta->get($productId);
            $products[] = [
                'productId' => $productId,
                'name' => $meta?->name ?? 'Unknown',
                'productNumber' => $meta?->product_number,
                'currentStock' => (float) ($stockByProduct[$productId] ?? 0),
                'shelfStatus' => $status,
                'earliestExpiry' => $info['earliestExpiry'] ?? null,
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
