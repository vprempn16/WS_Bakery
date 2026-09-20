<?php

namespace App\Modules\Api\V1\Recipe\Models;

use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{
    use \App\Traits\Auditable;
    use HasFactory, HasUuids;

    protected $fillable = [
        'product_id',
        'ingredient_id',
        'quantity_required',
        'quantity_pending',
    ];

    protected $casts = [
        'quantity_pending' => 'boolean',
        'quantity_required' => 'float',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function isPending(): bool
    {
        return (bool) $this->quantity_pending || $this->quantity_required === null;
    }

    /**
     * Derive product BOM status from recipe lines.
     *
     * @param  \Illuminate\Support\Collection<int, Recipe>|iterable<Recipe>  $recipes
     */
    public static function statusForRecipes(iterable $recipes): string
    {
        $list = collect($recipes);
        if ($list->isEmpty()) {
            return 'incomplete';
        }

        foreach ($list as $recipe) {
            if ($recipe->isPending()) {
                return 'incomplete';
            }
        }

        return 'complete';
    }
}
