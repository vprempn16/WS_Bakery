<?php

namespace App\Modules\Api\V1\User\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Modules\Api\V1\Organization\Models\Organization;

class User extends Authenticatable
{
    use \App\Traits\Auditable;
    use HasApiTokens, HasFactory, Notifiable, HasUuids;

    protected $fillable = [
        'organization_id',
        'branch_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'role',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch()
    {
        return $this->belongsTo(\App\Modules\Api\V1\Branch\Models\Branch::class);
    }
}
