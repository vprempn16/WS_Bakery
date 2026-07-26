<?php

namespace App\Modules\Api\V1\BranchTransfer\Models;

use App\Modules\Api\V1\Product\Models\Product;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BranchTransferItem extends Model
{
    use HasUuids;
    use \App\Traits\Auditable;

    protected $fillable = [
        'organization_id',
        'branch_transfer_id',
        'product_id',
        'quantity',
        'unit',
        'pieces',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'pieces' => 'decimal:2',
    ];

    public function transfer()
    {
        return $this->belongsTo(BranchTransfer::class, 'branch_transfer_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
