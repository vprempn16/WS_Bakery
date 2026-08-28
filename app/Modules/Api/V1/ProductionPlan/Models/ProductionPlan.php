<?php

namespace App\Modules\Api\V1\ProductionPlan\Models;

use App\Models\BKModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ProductionPlan extends BKModel
{
    use \App\Traits\Auditable;
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'plan_date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(ProductionPlanItem::class, 'production_plan_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Modules\Api\V1\User\Models\User::class, 'created_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Modules\Api\V1\User\Models\User::class, 'created_by');
    }
}
