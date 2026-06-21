<?php

namespace App\Modules\Api\V1\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\ProductionBatch\Models\ProductionBatch;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ExpiryReportController extends Controller
{
    public function expiringBatches(Request $request)
    {
        $orgId = $request->user()->organization_id;
        $now = Carbon::now();
        $warningThreshold = $now->copy()->addHours(24);

        // Fetch batches that are not fully sold out or wasted (assuming we track this by looking at overall product stock or if status is not 'wasted'.
        // For Phase 2, we just pull all batches and group them by expiry time. In the future, we could filter by remaining physical batch stock if tracked.
        $batches = ProductionBatch::with('product')
            ->where('organization_id', $orgId)
            ->where('status', '!=', 'wasted') // Example condition to exclude fully wasted ones
            ->orderBy('expiry_timestamp', 'asc')
            ->get();

        $expired = [];
        $expiringSoon = [];
        $healthy = [];

        foreach ($batches as $batch) {
            $expiry = $batch->expiry_timestamp;
            $data = [
                'id' => $batch->id,
                'batchNumber' => $batch->batch_number,
                'productName' => $batch->product ? $batch->product->name : 'Unknown',
                'tier' => $batch->product ? $batch->product->tier : 'N/A',
                'quantityProduced' => (float) $batch->quantity_produced,
                'productionDate' => $batch->production_date ? $batch->production_date->format('Y-m-d') : null,
                'expiryTimestamp' => $expiry ? $expiry->format('Y-m-d H:i:s') : null,
            ];

            if (!$expiry) {
                $healthy[] = $data;
            } elseif ($expiry->isPast()) {
                $expired[] = $data;
            } elseif ($expiry->lessThanOrEqualTo($warningThreshold)) {
                $expiringSoon[] = $data;
            } else {
                $healthy[] = $data;
            }
        }

        return $this->success([
            'summary' => [
                'expiredCount' => count($expired),
                'expiringSoonCount' => count($expiringSoon),
                'healthyCount' => count($healthy),
            ],
            'expired' => $expired,
            'expiringSoon' => $expiringSoon,
            'healthy' => $healthy,
        ], 'Expiry report fetched successfully.');
    }
}
