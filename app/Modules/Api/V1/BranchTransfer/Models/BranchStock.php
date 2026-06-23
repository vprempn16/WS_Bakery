<?php

namespace App\Modules\Api\V1\BranchTransfer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Modules\Api\V1\Branch\Models\Branch;
use App\Modules\Api\V1\Product\Models\Product;

class BranchStock extends Model
{
    use \App\Traits\Auditable;
    use HasUuids;

    protected $fillable = [
        'organization_id',
        'branch_id',
        'product_id',
        'current_stock',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
