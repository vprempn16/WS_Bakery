<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Shared Idempotency-Key handling for stock-mutating creates.
 */
class Idempotency
{
    /**
     * Begin an idempotent operation.
     *
     * @return array{0: ?Lock, 1: ?string, 2: ?JsonResponse}
     *   [lock, cacheKey, earlyResponse] — if earlyResponse is set, return it immediately.
     */
    public static function begin(string $scope, ?string $key, bool $required = true): array
    {
        if (! is_string($key) || $key === '') {
            if ($required) {
                return [null, null, response()->json([
                    'status' => false,
                    'message' => 'Idempotency-Key header is required.',
                    'data' => null,
                ], 422)];
            }

            return [null, null, null];
        }

        $userId = (string) (auth()->id() ?? 'anon');
        $cacheKey = 'idempotency:'.$scope.':'.$userId.':'.hash('sha256', $key);

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['body'], $cached['status'])) {
            return [null, $cacheKey, response()->json($cached['body'], $cached['status'])];
        }

        $lock = Cache::lock($cacheKey.':lock', 30);
        if (! $lock->get()) {
            for ($i = 0; $i < 20; $i++) {
                usleep(100000);
                $cached = Cache::get($cacheKey);
                if (is_array($cached) && isset($cached['body'], $cached['status'])) {
                    return [null, $cacheKey, response()->json($cached['body'], $cached['status'])];
                }
            }

            return [null, $cacheKey, response()->json([
                'status' => false,
                'message' => 'A matching request is already being processed. Please wait.',
                'data' => null,
            ], 409)];
        }

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['body'], $cached['status'])) {
            optional($lock)->release();

            return [null, $cacheKey, response()->json($cached['body'], $cached['status'])];
        }

        return [$lock, $cacheKey, null];
    }

    public static function remember(?string $cacheKey, JsonResponse $response): void
    {
        if (! $cacheKey) {
            return;
        }

        Cache::put($cacheKey, [
            'status' => $response->getStatusCode(),
            'body' => $response->getData(true),
        ], now()->addHours(24));
    }

    public static function release(?Lock $lock): void
    {
        optional($lock)->release();
    }
}
