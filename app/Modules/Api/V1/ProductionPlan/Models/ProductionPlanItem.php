<?php

namespace App\Modules\Api\V1\ProductionPlan\Models;

use App\Modules\Api\V1\Product\Models\Product;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProductionPlanItem extends Model
{
    use HasUuids;
    use \App\Traits\Auditable;

    protected $fillable = [
        'organization_id',
        'production_plan_id',
        'product_id',
        'planned_quantity',
        'produced_quantity',
    ];

    protected $casts = [
        'planned_quantity' => 'decimal:2',
        'produced_quantity' => 'decimal:2',
    ];

    public function plan()
    {
        return $this->belongsTo(ProductionPlan::class, 'production_plan_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
