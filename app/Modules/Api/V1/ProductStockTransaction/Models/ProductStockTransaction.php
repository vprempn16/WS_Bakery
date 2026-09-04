<?php

namespace App\Modules\Api\V1\ProductStockTransaction\Models;

use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\Product\Models\Product;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductStockTransaction extends Model
{
    use \App\Traits\Auditable;
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'product_id',
        'type',
        'quantity',
        'reference_note',
        'created_by',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
