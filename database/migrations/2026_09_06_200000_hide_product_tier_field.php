<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('crm_fields')
            ->where('modulename', 'products')
            ->where('fieldname', 'tier')
            ->where('deleted', 0)
            ->update(['displaytype' => 2, 'updated_at' => now()]);

        $all = DB::table('saved-filters')->where('module', 'products')->get();
        foreach ($all as $row) {
            $headers = json_decode($row->header_details ?? '[]', true);
            if (! is_array($headers)) {
                continue;
            }
            $next = array_values(array_filter($headers, function ($h) {
                $name = is_array($h) ? ($h['fieldname'] ?? $h['name'] ?? null) : $h;

                return strtolower((string) $name) !== 'tier';
            }));
            if (count($next) !== count($headers)) {
                DB::table('saved-filters')->where('id', $row->id)->update([
                    'header_details' => json_encode(array_values($next)),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('crm_fields')
            ->where('modulename', 'products')
            ->where('fieldname', 'tier')
            ->where('deleted', 0)
            ->update(['displaytype' => 1, 'updated_at' => now()]);
    }
};
