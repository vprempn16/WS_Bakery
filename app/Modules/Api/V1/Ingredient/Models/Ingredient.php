<?php

namespace App\Modules\Api\V1\Ingredient\Models;

use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'vendor_id',
        'name',
        'unit',
        'minimum_stock_level',
        'current_stock',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
