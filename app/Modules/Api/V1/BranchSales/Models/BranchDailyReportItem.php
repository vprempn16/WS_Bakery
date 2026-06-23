<?php

namespace App\Modules\Api\V1\BranchSales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Modules\Api\V1\Product\Models\Product;

class BranchDailyReportItem extends Model
{
    use \App\Traits\Auditable;
    use HasUuids;

    protected $fillable = [
        'branch_daily_report_id',
        'product_id',
        'quantity_sold',
        'quantity_returned',
        'unit_price',
        'subtotal_revenue',
        'subtotal_waste',
    ];

    protected $casts = [
        'quantity_sold' => 'decimal:2',
        'quantity_returned' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'subtotal_revenue' => 'decimal:2',
        'subtotal_waste' => 'decimal:2',
    ];

    public function report()
    {
        return $this->belongsTo(BranchDailyReport::class, 'branch_daily_report_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
