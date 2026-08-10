<?php

namespace App\Support;

use Illuminate\Http\Request;

class ApiPagination
{
    public const DEFAULT_PER_PAGE = 20;

    public const MAX_PER_PAGE = 100;

    /**
     * Clamp list page size to a safe maximum.
     */
    public static function perPage(Request $request, int $default = self::DEFAULT_PER_PAGE, int $max = self::MAX_PER_PAGE): int
    {
        $raw = $request->query('limit', $request->query('per_page', $default));
        $perPage = (int) $raw;

        if ($perPage < 1) {
            return $default;
        }

        return min($perPage, $max);
    }
}
