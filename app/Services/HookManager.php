<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Minimal bakery-safe hook runner (no CRM Lead/Invoice modules registered).
 */
class HookManager
{
    protected static array $hooks = [];

    public static function registerHook(string $module, string $event, callable $callback): void
    {
        self::$hooks[$module][$event][] = $callback;
    }

    public static function executeHook(string $module, string $event, array &$data): array
    {
        $hooks = self::$hooks[$module][$event] ?? [];
        $globalHooks = self::$hooks['*'][$event] ?? [];
        $allHooks = array_merge($globalHooks, $hooks);

        foreach ($allHooks as $callback) {
            try {
                $result = $callback($data);

                if (is_array($result) && ($result['error'] ?? false) === true) {
                    return $result;
                }
            } catch (\Exception $e) {
                Log::error("Error executing {$event} hook for {$module}", [
                    'error' => $e->getMessage(),
                    'module' => $module,
                    'event' => $event,
                ]);

                return ['error' => true, 'message' => $e->getMessage()];
            }
        }

        return ['error' => false];
    }
}
