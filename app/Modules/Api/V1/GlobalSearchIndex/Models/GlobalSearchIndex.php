<?php

namespace App\Modules\Api\V1\GlobalSearchIndex\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Optional global search index row. Table may be absent in bakery Phase 1.
 */
class GlobalSearchIndex extends Model
{
    public $incrementing = false;
    protected $table = 'global_search_index';
    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'module_name',
        'record_id',
        'label',
        'search_text',
        'more_info',
    ];

    protected $casts = [
        'more_info' => 'array',
    ];
}
