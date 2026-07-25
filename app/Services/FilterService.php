<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Bakery-safe filter helper. Saved CRM filters table is optional.
 */
class FilterService
{
    public static function applyFilter(Builder $query, string $filterId, ?string $moduleName = null): Builder
    {
        // No optional CRM filters table in bakery Phase 1 — leave query unchanged.
        Log::debug('FilterService::applyFilter skipped (filters table not used)', [
            'filter_id' => $filterId,
            'module' => $moduleName,
        ]);

        return $query;
    }

    public static function getFilteredList(string $moduleName, $filterId = null, int $perPage = 20, int $page = 1): array
    {
        $class = "\\App\\Modules\\Api\\V1\\{$moduleName}\\Models\\{$moduleName}";
        if (!class_exists($class)) {
            return [
                'list' => [],
                'meta' => [
                    'current_page' => $page,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                ],
                'links' => [],
            ];
        }

        $paginator = $class::query()->paginate($perPage, ['*'], 'page', $page);

        return ListResponseService::listViewFromPaginator($paginator);
    }

    public static function applyConditions(Builder $query, $conditions, string $moduleName): Builder
    {
        return $query;
    }
}
