<?php

namespace App\Modules\Api\V1\MaterialIssue\Models;

use App\Models\BKModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class MaterialIssue extends BKModel
{
    use \App\Traits\Auditable;
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'issue_date' => 'date',
    ];

    protected static function booted()
    {
        parent::booted();

        static::creating(function ($model) {
            if (empty($model->issue_number)) {
                $datePrefix = 'ISSUE-' . Carbon::now()->format('Ymd') . '-';
                $latest = self::where('issue_number', 'like', $datePrefix . '%')
                    ->orderBy('issue_number', 'desc')
                    ->first();

                if ($latest) {
                    $sequence = (int) substr($latest->issue_number, -3);
                    $newSequence = str_pad($sequence + 1, 3, '0', STR_PAD_LEFT);
                } else {
                    $newSequence = '001';
                }

                $model->issue_number = $datePrefix . $newSequence;
            }
        });
    }

    public function items()
    {
        return $this->hasMany(MaterialIssueItem::class, 'material_issue_id');
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
