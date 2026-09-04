<?php

namespace App\Modules\Api\V1\Product\Models;

use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\Product\Services\ProductNumberService;
use App\Modules\Api\V1\Recipe\Models\Recipe;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Validation\ValidationException;

class Product extends \App\Models\BKModel
{
    use \App\Traits\Auditable;
    use HasFactory, HasUuids;

    // Stock must only change via ProductionBatch / ProductStockTransaction / BranchTransfer / Billing flows.
    protected $guarded = ['id', 'organization_id', 'deleted', 'created_at', 'updated_at', 'created_by', 'current_stock'];

    protected $attributes = [
        'status' => 'active',
        'product_source' => 'own',
    ];

    protected $casts = [
        'shelf_life' => 'integer',
    ];

    public function isSellable(): bool
    {
        return strtolower((string) ($this->status ?? 'active')) === 'active';
    }

    public function isBought(): bool
    {
        return strtolower((string) ($this->product_source ?? 'own')) === 'bought';
    }

    public function isOwn(): bool
    {
        return ! $this->isBought();
    }

    protected static function booted()
    {
        static::creating(function ($product) {
            if (empty($product->status)) {
                $product->status = 'active';
            }
            if (empty($product->product_source)) {
                $product->product_source = 'own';
            }
        });

        static::saving(function ($product) {
            if ($product->status !== null && $product->status !== '') {
                $normalized = strtolower(trim((string) $product->status));
                $product->status = in_array($normalized, ['active', 'inactive'], true)
                    ? $normalized
                    : 'active';
            }

            if ($product->product_source !== null && $product->product_source !== '') {
                $src = strtolower(trim((string) $product->product_source));
                $product->product_source = in_array($src, ['own', 'bought'], true) ? $src : 'own';
            }

            if ($product->unit !== null && $product->unit !== '') {
                $u = strtolower(trim((string) $product->unit));
                if ($u === 'g') {
                    $u = 'gm';
                }
                $product->unit = $u;
            }

            if ($product->isDirty('product_number')) {
                if ($product->product_number !== null && trim((string) $product->product_number) !== '') {
                    // Digits only
                    if (! preg_match('/^\d+$/', trim((string) $product->product_number))) {
                        throw ValidationException::withMessages([
                            'data.values.productNumber' => ['Product number must contain digits only (no letters).'],
                        ]);
                    }

                    $normalized = ProductNumberService::normalize((string) $product->product_number);
                    if ($normalized !== null) {
                        $product->product_number = $normalized;
                    }

                    $orgId = $product->organization_id;
                    if ($orgId) {
                        $check = ProductNumberService::checkAvailability(
                            (string) $orgId,
                            (string) $product->product_number,
                            $product->exists ? (string) $product->id : null
                        );

                        if (!$check['available']) {
                            throw ValidationException::withMessages([
                                'data.values.productNumber' => [$check['message'] ?? 'Product number already exists'],
                            ]);
                        }
                    }
                }
            }
        });

        static::creating(function ($product) {
            if (empty($product->product_number)) {
                $orgId = $product->organization_id;
                $driver = \Illuminate\Support\Facades\DB::getDriverName();
                $base = \Illuminate\Support\Facades\DB::table('products')
                    ->when($orgId, fn ($q) => $q->where('organization_id', $orgId));

                if ($driver === 'sqlite') {
                    $maxNumber = (clone $base)
                        ->pluck('product_number')
                        ->filter(fn ($n) => is_string($n) && preg_match('/^\d+$/', $n))
                        ->map(fn ($n) => (int) $n)
                        ->max();
                } else {
                    $maxNumber = (clone $base)
                        ->whereRaw('product_number REGEXP "^[0-9]+$"')
                        ->selectRaw('MAX(CAST(product_number AS UNSIGNED)) as max_num')
                        ->value('max_num');
                }

                $nextNum = $maxNumber ? (int) $maxNumber + 1 : 1;

                while (
                    \Illuminate\Support\Facades\DB::table('products')
                        ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                        ->where('product_number', (string) $nextNum)
                        ->exists()
                ) {
                    $nextNum++;
                }

                $product->product_number = (string) $nextNum;
            }
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function recipes()
    {
        return $this->hasMany(Recipe::class);
    }

    public function productionBatches()
    {
        return $this->hasMany(\App\Modules\Api\V1\ProductionBatch\Models\ProductionBatch::class);
    }

    public function branchStocks()
    {
        return $this->hasMany(\App\Modules\Api\V1\BranchTransfer\Models\BranchStock::class);
    }

    public function branchTransferItems()
    {
        return $this->hasMany(\App\Modules\Api\V1\BranchTransfer\Models\BranchTransferItem::class);
    }

    public function dailyReportItems()
    {
        return $this->hasMany(\App\Modules\Api\V1\BranchSales\Models\BranchDailyReportItem::class);
    }

    public function stockTransactions()
    {
        return $this->hasMany(\App\Modules\Api\V1\ProductStockTransaction\Models\ProductStockTransaction::class);
    }
}
