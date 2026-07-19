<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bakery-safe global search helper.
 * Full global-search indexing is optional; CRUD must not crash when the table is absent.
 */
class GlobalSearchIndexService
{
    /**
     * Resolve a related record label from global_search_index when available.
     *
     * @return array{module_name: string, label: string}|null
     */
    public static function getModuleByRecordId(string $recordId, string $orgId): ?array
    {
        if ($recordId === '' || $orgId === '' || !Schema::hasTable('global_search_index')) {
            return null;
        }

        $query = DB::table('global_search_index')
            ->where('record_id', $recordId)
            ->where('organization_id', $orgId);

        if (Schema::hasColumn('global_search_index', 'deleted')) {
            $query->where('deleted', 0);
        }

        $row = $query->first(['module_name', 'label']);
        if (!$row) {
            return null;
        }

        return [
            'module_name' => $row->module_name,
            'label' => $row->label ?? '',
        ];
    }

    /**
     * No-op upsert for bakery (index table not required for Phase 1).
     */
    public function upsert(string $orgId, string $module, string $recordId, string $label, string $searchText, ?array $moreInfo): void
    {
        if (!Schema::hasTable('global_search_index')) {
            return;
        }
        // Intentionally not implemented for bakery Phase 1.
    }
}
