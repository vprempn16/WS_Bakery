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

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
