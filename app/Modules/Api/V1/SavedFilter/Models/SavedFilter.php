<?php

namespace App\Modules\Api\V1\SavedFilter\Models;

use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\User\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavedFilter extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'saved-filters';

    protected $fillable = [
        'organization_id',
        'user_id',
        'name',
        'module',
        'rules',
        'is_public',
        'is_default',
        'header_details',
    ];

    protected $casts = [
        'rules' => 'array',
        'is_public' => 'boolean',
        'is_default' => 'boolean',
        'header_details' => 'array',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
