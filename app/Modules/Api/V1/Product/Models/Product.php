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

    // Stock must only change via ProductionBatch / BranchTransfer / Billing flows.
    protected $guarded = ['id', 'organization_id', 'deleted', 'created_at', 'updated_at', 'created_by', 'current_stock'];

    protected static function booted()
    {
        static::saving(function ($product) {
            if ($product->product_number === null || trim((string) $product->product_number) === '') {
                return;
            }

            $normalized = ProductNumberService::normalize((string) $product->product_number);
            if ($normalized !== null) {
                $product->product_number = $normalized;
            }

            $orgId = $product->organization_id;
            if (!$orgId) {
                return;
            }

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
        });

        static::creating(function ($product) {
            if (empty($product->product_number)) {
                $driver = \Illuminate\Support\Facades\DB::getDriverName();
                if ($driver === 'sqlite') {
                    $maxNumber = \Illuminate\Support\Facades\DB::table('products')
                        ->pluck('product_number')
                        ->filter(fn ($n) => is_string($n) && preg_match('/^\d+$/', $n))
                        ->map(fn ($n) => (int) $n)
                        ->max();
                } else {
                    $maxNumber = \Illuminate\Support\Facades\DB::table('products')
                        ->whereRaw('product_number REGEXP "^[0-9]+$"')
                        ->selectRaw('MAX(CAST(product_number AS UNSIGNED)) as max_num')
                        ->value('max_num');
                }

                $nextNum = $maxNumber ? (int) $maxNumber + 1 : 1;

                while (\Illuminate\Support\Facades\DB::table('products')->where('product_number', (string) $nextNum)->exists()) {
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
}
