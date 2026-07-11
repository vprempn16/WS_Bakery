<?php

namespace App\Modules\Api\V1\Product\Models;

use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\Recipe\Models\Recipe;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends \App\Models\BaseModel
{
    use \App\Traits\Auditable;
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected static function booted()
    {
        static::creating(function ($product) {
            if (empty($product->product_number)) {
                $maxNumber = \Illuminate\Support\Facades\DB::table('products')
                    ->whereRaw('product_number REGEXP "^[0-9]+$"')
                    ->selectRaw('MAX(CAST(product_number AS UNSIGNED)) as max_num')
                    ->value('max_num');

                $nextNum = $maxNumber ? (int)$maxNumber + 1 : 1;
                
                while (\Illuminate\Support\Facades\DB::table('products')->where('product_number', (string)$nextNum)->exists()) {
                    $nextNum++;
                }

                $product->product_number = (string)$nextNum;
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

    public function branchTransfers()
    {
        return $this->hasMany(\App\Modules\Api\V1\BranchTransfer\Models\BranchTransfer::class);
    }

    public function dailyReportItems()
    {
        return $this->hasMany(\App\Modules\Api\V1\BranchSales\Models\BranchDailyReportItem::class);
    }
}
