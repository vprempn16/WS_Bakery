<?php

namespace App\Modules\Api\V1\Branch\Models;

use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\User\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'name',
        'type',
        'address',
        'phone',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
