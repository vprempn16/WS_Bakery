<?php
namespace App\Modules\Api\V1\Profile\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Profile extends Model
{
    use HasFactory;

    protected $table = 'profiles';

    /** @var bool Profiles use manually assigned integer ids, not auto-increment */
    public $incrementing = false;

    /** @var string */
    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'organization_id',
        'name',
        'description',
        'status',
        'deleted'
    ];
    public function scopeFilter(Builder $builder, $filters)
        {
                return $filters->apply($builder);
        }
}

