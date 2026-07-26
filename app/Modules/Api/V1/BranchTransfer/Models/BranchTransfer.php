<?php

namespace App\Modules\Api\V1\BranchTransfer\Models;

use App\Models\BKModel;
use App\Modules\Api\V1\Branch\Models\Branch;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class BranchTransfer extends BKModel
{
    use \App\Traits\Auditable;
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'transfer_date' => 'date',
    ];

    protected static function booted()
    {
        parent::booted();

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

    public function items()
    {
        return $this->hasMany(BranchTransferItem::class, 'branch_transfer_id');
    }
}
