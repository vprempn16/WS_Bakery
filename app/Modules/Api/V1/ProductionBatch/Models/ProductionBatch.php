<?php

namespace App\Modules\Api\V1\ProductionBatch\Models;

use App\Models\BKModel;
use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\User\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductionBatch extends BKModel
{
    use \App\Traits\Auditable;
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'quantity_produced' => 'decimal:2',
        'pieces' => 'integer',
        'production_date' => 'date',
        'expiry_timestamp' => 'datetime',
    ];

    protected static function booted()
    {
        parent::booted();

        static::creating(function ($model) {
            if (empty($model->batch_number)) {
                $datePrefix = date('Ymd');
                $lastBatch = self::where('batch_number', 'like', "BATCH-{$datePrefix}-%")
                    ->orderBy('batch_number', 'desc')
                    ->first();

                if ($lastBatch) {
                    $lastSequence = (int) substr($lastBatch->batch_number, -3);
                    $newSequence = str_pad($lastSequence + 1, 3, '0', STR_PAD_LEFT);
                } else {
                    $newSequence = '001';
                }

                $model->batch_number = "BATCH-{$datePrefix}-{$newSequence}";
            }
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
