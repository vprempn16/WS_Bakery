<?php

namespace App\Modules\Api\V1\InventoryTransaction\Models;

use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    use \App\Traits\Auditable;
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'ingredient_id',
        'type', // 'in', 'out', 'waste', 'production'
        'quantity',
        'reference_note',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
