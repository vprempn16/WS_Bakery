<?php

namespace App\Modules\Api\V1\Vendor\Models;

use App\Models\BKModel;
use App\Modules\Api\V1\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Vendor extends BKModel
{
    use \App\Traits\Auditable;
    use HasFactory, HasUuids;

    protected $guarded = [];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
