<?php

namespace App\Modules\Api\V1\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Modules\Api\V1\Product\Models\Product;

class BillingItem extends Model
{
    use HasUuids, \App\Traits\Auditable;

    protected $fillable = [
        'billing_id',
        'product_id',
        'quantity',
        'unit_price',
        'total_price',
        'unit',
        'category',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function billing()
    {
        return $this->belongsTo(Billing::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
