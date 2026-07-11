<?php

namespace App\Modules\Api\V1\Ingredient\Models;

use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ingredient extends \App\Models\BaseModel
{
    use \App\Traits\Auditable;
    use HasFactory, HasUuids;

    protected $guarded = [];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
