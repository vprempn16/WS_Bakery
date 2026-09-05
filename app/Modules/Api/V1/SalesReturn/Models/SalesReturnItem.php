<?php

namespace App\Modules\Api\V1\SalesReturn\Models;

use App\Modules\Api\V1\Product\Models\Product;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SalesReturnItem extends Model
{
    use HasUuids;
    use \App\Traits\Auditable;

    protected $table = 'sales_return_items';

    protected $fillable = [
        'organization_id',
        'sales_return_id',
        'product_id',
        'quantity',
        'unit',
        'pieces',
        'unit_price',
        'return_value',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'pieces' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'return_value' => 'decimal:2',
    ];

    public function salesReturn()
    {
        return $this->belongsTo(SalesReturn::class, 'sales_return_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
