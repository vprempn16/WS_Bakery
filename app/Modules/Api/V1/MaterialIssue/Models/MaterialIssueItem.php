<?php

namespace App\Modules\Api\V1\MaterialIssue\Models;

use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MaterialIssueItem extends Model
{
    use HasUuids;
    use \App\Traits\Auditable;

    protected $fillable = [
        'organization_id',
        'material_issue_id',
        'ingredient_id',
        'quantity',
        'unit',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function issue()
    {
        return $this->belongsTo(MaterialIssue::class, 'material_issue_id');
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
