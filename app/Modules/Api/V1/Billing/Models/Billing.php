<?php

namespace App\Modules\Api\V1\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Modules\Api\V1\Branch\Models\Branch;

class Billing extends \App\Models\BKModel
{
    use HasUuids, \App\Traits\Auditable;

    protected $guarded = [
        'id',
        'organization_id',
        'deleted',
        'created_at',
        'updated_at',
        'created_by',
        'sub_total',
        'grand_total',
        'bill_number',
    ];

    protected $casts = [
        'billing_date' => 'datetime',
        'sub_total' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function items()
    {
        return $this->hasMany(BillingItem::class);
    }
}
