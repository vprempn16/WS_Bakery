<?php

namespace App\Modules\Api\V1\Vendor\Models;

use App\Modules\Api\V1\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use \App\Traits\Auditable;
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
