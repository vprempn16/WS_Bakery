<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmField extends Model
{
    protected $table = 'crm_fields';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'modulename',
        'fieldname',
        'fieldlabel',
        'fieldtype',
        'tablename',
        'mandatory',
        'apifieldname',
        'displaytype',
        'is_custom_field',
        'organization_id',
        'seq',
        'deleted',
    ];
}
