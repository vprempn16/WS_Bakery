<?php

namespace App\Modules\Api\V1\Product\Models;

use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\Recipe\Models\Recipe;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'product_number',
        'name',
        'description',
        'price',
        'unit',
        'shelf_life_days',
        'current_stock',
    ];

    protected static function booted()
    {
        static::creating(function ($product) {
            if (empty($product->product_number)) {
                $latestProduct = static::where('product_number', 'LIKE', 'PROD%')
                    ->orderBy('created_at', 'desc')
                    ->first();

                $nextNum = 1;
                if ($latestProduct) {
                    if (preg_match('/PROD(\d+)$/', $latestProduct->product_number, $matches)) {
                        $nextNum = (int)$matches[1] + 1;
                    }
                }

                $product->product_number = 'PROD' . $nextNum;
            }
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function recipes()
    {
        return $this->hasMany(Recipe::class);
    }
}
