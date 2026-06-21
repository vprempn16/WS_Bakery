<?php

namespace App\Modules\Api\V1\BranchTransfer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Modules\Api\V1\Branch\Models\Branch;
use App\Modules\Api\V1\Product\Models\Product;
use Carbon\Carbon;

class BranchTransfer extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id',
        'branch_id',
        'product_id',
        'transfer_number',
        'quantity',
        'transfer_date',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'transfer_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->transfer_number)) {
                $datePrefix = 'TRN-' . Carbon::now()->format('Ymd') . '-';
                $latest = self::where('transfer_number', 'like', $datePrefix . '%')
                    ->orderBy('transfer_number', 'desc')
                    ->first();

                if ($latest) {
                    $sequence = (int) substr($latest->transfer_number, -3);
                    $newSequence = str_pad($sequence + 1, 3, '0', STR_PAD_LEFT);
                } else {
                    $newSequence = '001';
                }

                $model->transfer_number = $datePrefix . $newSequence;
            }
        });
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
