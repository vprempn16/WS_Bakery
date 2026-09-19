<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Production batch lists: expiry date/time + shelf status (Fresh / Expiring Soon / Expired).
     */
    public function up(): void
    {
        if (Schema::hasTable('saved-filters')) {
            $rows = DB::table('saved-filters')
                ->whereIn('module', ['production_batches', 'ProductionBatch'])
                ->get(['id', 'header_details']);

            foreach ($rows as $row) {
                $headers = json_decode($row->header_details ?? '[]', true);
                if (! is_array($headers) || $headers === []) {
                    continue;
                }

                $next = $this->withExpiryColumns($headers);
                DB::table('saved-filters')->where('id', $row->id)->update([
                    'header_details' => json_encode($next),
                    'updated_at' => now(),
                ]);
            }
        }

        if (! Schema::hasTable('crm_fields')) {
            return;
        }

        $now = now();
        $moduleName = 'ProductionBatch';
        $def = [
            'apifieldname' => 'shelfStatus',
            'fieldname' => 'shelfStatus',
            'fieldlabel' => 'Shelf Status',
            'fieldtype' => 'text',
        ];

        $exists = DB::table('crm_fields')
            ->where('modulename', $moduleName)
            ->where(function ($q) use ($def) {
                $q->where('apifieldname', $def['apifieldname'])
                    ->orWhere('fieldname', $def['fieldname']);
            })
            ->where('deleted', 0)
            ->exists();

        if ($exists) {
            DB::table('crm_fields')
                ->where('modulename', $moduleName)
                ->where(function ($q) use ($def) {
                    $q->where('apifieldname', $def['apifieldname'])
                        ->orWhere('fieldname', $def['fieldname']);
                })
                ->update([
                    'fieldlabel' => $def['fieldlabel'],
                    'fieldtype' => $def['fieldtype'],
                    'displaytype' => 3,
                    'mandatory' => 0,
                    'deleted' => 0,
                    'updated_at' => $now,
                ]);

            return;
        }

        $maxSeq = (int) DB::table('crm_fields')
            ->where('modulename', $moduleName)
            ->where('deleted', 0)
            ->max('seq');

        DB::table('crm_fields')->insert([
            'id' => Str::uuid()->toString(),
            'modulename' => $moduleName,
            'fieldname' => $def['fieldname'],
            'fieldlabel' => $def['fieldlabel'],
            'fieldtype' => $def['fieldtype'],
            'tablename' => 'production_batches',
            'mandatory' => 0,
            'apifieldname' => $def['apifieldname'],
            'displaytype' => 3,
            'is_custom_field' => 0,
            'seq' => $maxSeq + 1,
            'deleted' => 0,
            'organization_id' => 'default',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $headers
     * @return list<array<string, mixed>>
     */
    private function withExpiryColumns(array $headers): array
    {
        $inject = [
            ['fieldname' => 'shelfStatus', 'fieldlabel' => 'Shelf Status'],
            ['fieldname' => 'expiryDate', 'fieldlabel' => 'Expiry Date'],
            ['fieldname' => 'expiryTime', 'fieldlabel' => 'Expiry Time'],
        ];

        $present = [];
        $out = [];
        $injected = false;

        foreach ($headers as $header) {
            $name = $header['fieldname'] ?? null;
            if ($name === 'expiryTimestamp') {
                continue;
            }
            if ($name === 'productionDate') {
                $header['fieldlabel'] = 'Production Date & Time';
            }
            $out[] = $header;
            if (is_string($name) && $name !== '') {
                $present[$name] = true;
            }
            if ($name === 'productionDate') {
                foreach ($inject as $col) {
                    if (! isset($present[$col['fieldname']])) {
                        $out[] = $col;
                        $present[$col['fieldname']] = true;
                    }
                }
                $injected = true;
            }
        }

        if (! $injected) {
            foreach ($inject as $col) {
                if (! isset($present[$col['fieldname']])) {
                    $out[] = $col;
                }
            }
        }

        return array_values($out);
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_fields')) {
            DB::table('crm_fields')
                ->where('modulename', 'ProductionBatch')
                ->where('apifieldname', 'shelfStatus')
                ->update(['deleted' => 1, 'updated_at' => now()]);
        }
    }
};
