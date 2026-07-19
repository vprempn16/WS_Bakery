<?php

namespace App\Modules\Api\V1\Branch\Models;

use App\Models\BKModel;
use App\Modules\Api\V1\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Branch extends BKModel
{
    use \App\Traits\Auditable;
    use HasFactory, HasUuids;

    protected $guarded = [];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function users()
    {
        return $this->hasMany(\App\Modules\Api\V1\User\Models\User::class);
    }

    public function stocks()
    {
        return $this->hasMany(\App\Modules\Api\V1\BranchTransfer\Models\BranchStock::class);
    }

    public function transfers()
    {
        return $this->hasMany(\App\Modules\Api\V1\BranchTransfer\Models\BranchTransfer::class);
    }

    public function dailyReports()
    {
        return $this->hasMany(\App\Modules\Api\V1\BranchSales\Models\BranchDailyReport::class);
    }
}
