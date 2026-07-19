<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Bakery-safe numbering. Uses module_numbering_details when present; otherwise a timestamp fallback.
 */
class ModuleNumberingService
{
    public static function generateNumber(string $module, ?string $orgId): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $module) ?: 'REC', 0, 3));

        if (!$orgId || !Schema::hasTable('module_numbering_details')) {
            return $prefix . date('ymdHis');
        }

        return DB::transaction(function () use ($module, $orgId, $prefix) {
            $entry = DB::table('module_numbering_details')
                ->where('module_name', $module)
                ->where('organization_id', $orgId)
                ->lockForUpdate()
                ->first();

            if (!$entry) {
                $id = (string) Str::uuid();
                DB::table('module_numbering_details')->insert([
                    'id' => $id,
                    'organization_id' => $orgId,
                    'module_name' => $module,
                    'prefix' => $prefix,
                    'initial_suffix' => 1,
                    'current_suffix' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $prefix . str_pad('1', 4, '0', STR_PAD_LEFT);
            }

            $suffix = (int) ($entry->current_suffix ?? 1);
            DB::table('module_numbering_details')
                ->where('id', $entry->id)
                ->update([
                    'current_suffix' => $suffix + 1,
                    'updated_at' => now(),
                ]);

            $entryPrefix = $entry->prefix ?: $prefix;

            return $entryPrefix . str_pad((string) $suffix, 4, '0', STR_PAD_LEFT);
        });
    }
}
