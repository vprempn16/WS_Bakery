<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmDefaultFieldDefinition extends Model
{
    protected $table = 'crm_default_field_definitions';

    protected $fillable = [
        'organization_id',
        'modulename',
        'fieldname',
        'fieldlabel',
        'mandatory',
        'seq',
    ];
}
