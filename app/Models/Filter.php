<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Optional CRM filters model (table: filters).
 * Bakery Phase 1 uses SavedFilter (saved-filters) for list filtering;
 * this class exists so BKModel::getList can resolve without class-not-found.
 */
class Filter extends Model
{
    public $incrementing = false;
    protected $table = 'filters';
    protected $keyType = 'string';
    protected $guarded = [];

    protected $casts = [
        'is_shared' => 'boolean',
        'is_default' => 'boolean',
        'deleted' => 'boolean',
        'header_details' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (!isset($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
