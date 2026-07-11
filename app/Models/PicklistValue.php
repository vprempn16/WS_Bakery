<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PicklistValue extends Model
{
    protected $table = 'picklist_values';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'field_id',
        'label',
        'value',
        'sort_order',
        'status',
    ];
}
