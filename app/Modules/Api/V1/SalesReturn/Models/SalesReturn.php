<?php

namespace App\Modules\Api\V1\SalesReturn\Models;

use App\Models\BKModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SalesReturn extends BKModel
{
    use \App\Traits\Auditable;
    use HasUuids;

    protected $table = 'sales_returns';

    protected $guarded = [];

    protected $casts = [
        'return_date' => 'date',
        'total_return_value' => 'decimal:2',
    ];

    protected static function booted()
    {
        parent::booted();

        static::creating(function ($model) {
            if (empty($model->return_number)) {
                $datePrefix = 'RET-' . Carbon::now()->format('Ymd') . '-';
                $latest = self::where('return_number', 'like', $datePrefix . '%')
                    ->orderBy('return_number', 'desc')
                    ->first();

                if ($latest) {
                    $sequence = (int) substr($latest->return_number, -3);
                    $newSequence = str_pad((string) ($sequence + 1), 3, '0', STR_PAD_LEFT);
                } else {
                    $newSequence = '001';
                }

                $model->return_number = $datePrefix . $newSequence;
            }
        });
    }

    public function items()
    {
        return $this->hasMany(SalesReturnItem::class, 'sales_return_id');
    }

    public function branch()
    {
        return $this->belongsTo(\App\Modules\Api\V1\Branch\Models\Branch::class);
    }

    public function creator()
    {
        return $this->belongsTo(\App\Modules\Api\V1\User\Models\User::class, 'created_by');
    }
}
