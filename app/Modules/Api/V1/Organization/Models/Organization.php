<?php

namespace App\Modules\Api\V1\Organization\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Modules\Api\V1\User\Models\User;

class Organization extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'description',
        'email',
        'phone',
        'address',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
