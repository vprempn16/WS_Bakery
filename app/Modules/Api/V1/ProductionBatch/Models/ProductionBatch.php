<?php

namespace App\Modules\Api\V1\ProductionBatch\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\User\Models\User;

class ProductionBatch extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'batch_number',
        'product_id',
        'quantity_produced',
        'production_date',
        'expiry_date',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity_produced' => 'decimal:2',
        'production_date' => 'date',
        'expiry_date' => 'date',
    ];

    /**
     * Boot function to assign batch number automatically.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->batch_number)) {
                $datePrefix = date('Ymd');
                // Get the last batch number for today to auto-increment
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
