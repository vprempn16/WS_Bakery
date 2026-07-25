<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $modules = [
            'branches' => [
                ['fieldname' => 'id', 'fieldlabel' => 'ID'],
                ['fieldname' => 'name', 'fieldlabel' => 'Name'],
                ['fieldname' => 'type', 'fieldlabel' => 'Type'],
                ['fieldname' => 'address', 'fieldlabel' => 'Address'],
                ['fieldname' => 'phone', 'fieldlabel' => 'Phone'],
                ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At'],
            ],
            'branch_transfers' => [
                ['fieldname' => 'id', 'fieldlabel' => 'ID'],
                ['fieldname' => 'transferNumber', 'fieldlabel' => 'Transfer Number'],
                ['fieldname' => 'branchId', 'fieldlabel' => 'Branch ID'],
                ['fieldname' => 'productId', 'fieldlabel' => 'Product ID'],
                ['fieldname' => 'quantity', 'fieldlabel' => 'Quantity'],
                ['fieldname' => 'transferDate', 'fieldlabel' => 'Transfer Date'],
                ['fieldname' => 'status', 'fieldlabel' => 'Status'],
                ['fieldname' => 'notes', 'fieldlabel' => 'Notes'],
                ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At'],
            ],
            'production_batches' => [
                ['fieldname' => 'id', 'fieldlabel' => 'ID'],
                ['fieldname' => 'batchNumber', 'fieldlabel' => 'Batch Number'],
                ['fieldname' => 'productId', 'fieldlabel' => 'Product ID'],
                ['fieldname' => 'quantityProduced', 'fieldlabel' => 'Quantity Produced'],
                ['fieldname' => 'productionDate', 'fieldlabel' => 'Production Date'],
                ['fieldname' => 'expiryTimestamp', 'fieldlabel' => 'Expiry Timestamp'],
                ['fieldname' => 'status', 'fieldlabel' => 'Status'],
                ['fieldname' => 'notes', 'fieldlabel' => 'Notes'],
                ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At'],
            ],
        ];

        $now = now();
        foreach ($modules as $moduleName => $fields) {
            DB::table('saved-filters')->insert([
                'id' => Str::uuid()->toString(),
                'organization_id' => null,
                'user_id' => null,
                'name' => 'All',
                'module' => $moduleName,
                'rules' => json_encode([]),
                'is_public' => true,
                'is_default' => true,
                'header_details' => json_encode($fields),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('saved-filters')
            ->whereIn('module', ['branches', 'branch_transfers', 'production_batches'])
            ->where('is_default', true)
            ->delete();
    }
};
