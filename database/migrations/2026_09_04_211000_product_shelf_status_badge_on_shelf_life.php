<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Product list: drop separate Shelf Status column; badge sits on Shelf Life.
     */
    public function up(): void
    {
        $headers = [
            ['fieldname' => 'productNumber', 'fieldlabel' => 'Product Number'],
            ['fieldname' => 'name', 'fieldlabel' => 'Name'],
            ['fieldname' => 'price', 'fieldlabel' => 'Price'],
            ['fieldname' => 'unit', 'fieldlabel' => 'Unit'],
            ['fieldname' => 'status', 'fieldlabel' => 'Status'],
            ['fieldname' => 'shelfLife', 'fieldlabel' => 'Shelf Life'],
            ['fieldname' => 'currentStock', 'fieldlabel' => 'Current Stock'],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At'],
        ];

        DB::table('saved-filters')
            ->where('module', 'products')
            ->where('is_default', true)
            ->update([
                'header_details' => json_encode($headers),
                'updated_at' => now(),
            ]);

        // Also strip shelfStatus from any org default/custom product filters
        $filters = DB::table('saved-filters')
            ->where('module', 'products')
            ->get(['id', 'header_details']);

        foreach ($filters as $filter) {
            $details = json_decode($filter->header_details ?? '[]', true);
            if (! is_array($details)) {
                continue;
            }
            $updated = array_values(array_filter(
                $details,
                fn ($h) => ($h['fieldname'] ?? '') !== 'shelfStatus'
            ));
            if (count($updated) !== count($details)) {
                DB::table('saved-filters')
                    ->where('id', $filter->id)
                    ->update([
                        'header_details' => json_encode($updated),
                        'updated_at' => now(),
                    ]);
            }
        }

        if (! Schema::hasTable('crm_fields')) {
            return;
        }

        DB::table('crm_fields')
            ->where('modulename', 'Product')
            ->where('apifieldname', 'shelfStatus')
            ->update([
                'displaytype' => 2,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // no-op — prior migration restored a dedicated column if re-run
    }
};
