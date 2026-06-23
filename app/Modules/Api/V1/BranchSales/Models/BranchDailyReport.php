<?php

namespace App\Modules\Api\V1\BranchSales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Modules\Api\V1\Branch\Models\Branch;

class BranchDailyReport extends Model
{
    use \App\Traits\Auditable;
    use HasUuids;

    protected $fillable = [
        'organization_id',
        'branch_id',
        'report_date',
        'total_revenue',
        'total_waste_amount',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'report_date' => 'date',
        'total_revenue' => 'decimal:2',
        'total_waste_amount' => 'decimal:2',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function items()
    {
        return $this->hasMany(BranchDailyReportItem::class);
    }
}
