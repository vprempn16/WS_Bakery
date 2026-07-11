<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleRelationFields extends Model
{
    protected $table = 'module_relation_fields';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'field_id',
        'modulename',
        'related_module',
        'deleted',
    ];
}
