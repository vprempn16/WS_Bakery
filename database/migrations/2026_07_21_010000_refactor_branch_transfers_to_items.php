<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_transfer_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('branch_transfer_id')->constrained('branch_transfers')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 10, 2);
            $table->string('unit')->nullable();
            $table->decimal('pieces', 10, 2)->nullable();
            $table->timestamps();

            $table->index('branch_transfer_id');
        });

        // Backfill: each old transfer row becomes one item
        $transfers = DB::table('branch_transfers')->get();
        foreach ($transfers as $transfer) {
            if (empty($transfer->product_id)) {
                continue;
            }
            $unit = DB::table('products')->where('id', $transfer->product_id)->value('unit');
            DB::table('branch_transfer_items')->insert([
                'id' => (string) Str::uuid(),
                'organization_id' => $transfer->organization_id,
                'branch_transfer_id' => $transfer->id,
                'product_id' => $transfer->product_id,
                'quantity' => $transfer->quantity,
                'unit' => $unit,
                'pieces' => null,
                'created_at' => $transfer->created_at ?? now(),
                'updated_at' => $transfer->updated_at ?? now(),
            ]);
        }

        Schema::table('branch_transfers', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn(['product_id', 'quantity']);
        });

        // Child portal module (hidden from Profile via parent_module_id)
        $parentId = DB::table('portal_module')->where('modulename', 'BranchTransfer')->value('id');
        if ($parentId) {
            $exists = DB::table('portal_module')->where('modulename', 'BranchTransferItem')->exists();
            if (!$exists) {
                DB::table('portal_module')->insert([
                    'id' => (string) Str::uuid(),
                    'modulename' => 'BranchTransferItem',
                    'modulelabel' => 'Branch Transfer Item',
                    'is_entity' => 1,
                    'is_email' => 0,
                    'is_phone' => 0,
                    'status' => 'Active',
                    'sort_order' => 8,
                    'account_id' => 'all',
                    'is_system_default' => 1,
                    'parent_module_id' => $parentId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Refresh default list headers for branch_transfers
        $defaultHeaders = json_encode([
            ['fieldname' => 'id', 'fieldlabel' => 'ID'],
            ['fieldname' => 'transferNumber', 'fieldlabel' => 'Transfer Number'],
            ['fieldname' => 'branchId', 'fieldlabel' => 'To Branch'],
            ['fieldname' => 'transferDate', 'fieldlabel' => 'Transfer Date'],
            ['fieldname' => 'status', 'fieldlabel' => 'Status'],
            ['fieldname' => 'notes', 'fieldlabel' => 'Notes'],
            ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At'],
        ]);

        DB::table('saved-filters')
            ->where('module', 'branch_transfers')
            ->where('is_default', true)
            ->update([
                'header_details' => $defaultHeaders,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('branch_transfers', function (Blueprint $table) {
            $table->foreignUuid('product_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 10, 2)->nullable();
        });

        $items = DB::table('branch_transfer_items')->orderBy('created_at')->get();
        $seen = [];
        foreach ($items as $item) {
            if (isset($seen[$item->branch_transfer_id])) {
                continue;
            }
            $seen[$item->branch_transfer_id] = true;
            DB::table('branch_transfers')->where('id', $item->branch_transfer_id)->update([
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
            ]);
        }

        Schema::dropIfExists('branch_transfer_items');
        DB::table('portal_module')->where('modulename', 'BranchTransferItem')->delete();
    }
};
