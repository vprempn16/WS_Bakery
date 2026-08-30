<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaultHeaders = json_encode([
            ['fieldname' => 'id', 'fieldlabel' => 'ID'],
            ['fieldname' => 'batchNumber', 'fieldlabel' => 'Batch Number'],
            ['fieldname' => 'productId', 'fieldlabel' => 'Product'],
            ['fieldname' => 'quantityProduced', 'fieldlabel' => 'Quantity Produced'],
            ['fieldname' => 'pieces', 'fieldlabel' => 'Pieces'],
            ['fieldname' => 'productionDate', 'fieldlabel' => 'Production Date'],
            ['fieldname' => 'expiryTimestamp', 'fieldlabel' => 'Expiry Timestamp'],
            ['fieldname' => 'status', 'fieldlabel' => 'Status'],
            ['fieldname' => 'notes', 'fieldlabel' => 'Notes'],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At'],
        ]);

        DB::table('saved-filters')
            ->where('module', 'production_batches')
            ->where('is_default', true)
            ->update([
                'header_details' => $defaultHeaders,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $defaultHeaders = json_encode([
            ['fieldname' => 'id', 'fieldlabel' => 'ID'],
            ['fieldname' => 'batchNumber', 'fieldlabel' => 'Batch Number'],
            ['fieldname' => 'productId', 'fieldlabel' => 'Product ID'],
            ['fieldname' => 'quantityProduced', 'fieldlabel' => 'Quantity Produced'],
            ['fieldname' => 'productionDate', 'fieldlabel' => 'Production Date'],
            ['fieldname' => 'expiryTimestamp', 'fieldlabel' => 'Expiry Timestamp'],
            ['fieldname' => 'status', 'fieldlabel' => 'Status'],
            ['fieldname' => 'notes', 'fieldlabel' => 'Notes'],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At'],
        ]);

        DB::table('saved-filters')
            ->where('module', 'production_batches')
            ->where('is_default', true)
            ->update([
                'header_details' => $defaultHeaders,
                'updated_at' => now(),
            ]);
    }
};
