<?php

namespace App\Modules\Api\V1\Recipe\Models;

use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'product_id',
        'ingredient_id',
        'quantity_required',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
