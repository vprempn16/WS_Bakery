<?php

namespace App\Modules\Api\V1\Ingredient\Models;

use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ingredient extends \App\Models\BKModel
{
    use \App\Traits\Auditable;
    use HasFactory, HasUuids;

    // Inherit BKModel guarded (id, organization_id, deleted, timestamps, created_by).
    // Do not allow mass-assign of stock via API fill — stock changes go through InventoryTransaction.
    protected $guarded = ['id', 'organization_id', 'deleted', 'created_at', 'updated_at', 'created_by', 'current_stock'];

    protected $attributes = [
        'category' => 'raw',
    ];

    protected static function booted()
    {
        static::creating(function ($ingredient) {
            if (empty($ingredient->category)) {
                $ingredient->category = 'raw';
            }
        });

        static::saving(function ($ingredient) {
            if ($ingredient->category !== null && $ingredient->category !== '') {
                $cat = strtolower(trim((string) $ingredient->category));
                $ingredient->category = in_array($cat, ['raw', 'packaging', 'other'], true) ? $cat : 'raw';
            }
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
