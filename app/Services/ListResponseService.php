<?php

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;

class ListResponseService
{
    public static function format(LengthAwarePaginator $paginator, ?callable $transformCallback = null): array
    {
        $collection = $paginator->getCollection();

        if ($transformCallback) {
            $collection = $collection->map($transformCallback);
        }

        return [
            'details' => $collection->values(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * @return array{list: array, meta: array, links: array}
     */
    public static function listViewFromPaginator(LengthAwarePaginator $paginator): array
    {
        return [
            'list' => $paginator->getCollection()->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ];
    }
}
