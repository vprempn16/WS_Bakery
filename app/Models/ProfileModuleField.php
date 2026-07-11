<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileModuleField extends Model
{
    protected $table = 'profile_module_fields';

    protected $fillable = [
        'profileid',
        'modulename',
        'field_id',
        'organization_id',
        'invisible',
        'editable',
        'readonly',
    ];
}
