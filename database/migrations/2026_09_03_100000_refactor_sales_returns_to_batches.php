<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Migrates legacy flat sales_returns (one product per row) to header + items.
 * No-op when sales_returns was already created in batch form (no product_id column).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_returns')) {
            return;
        }

        // Fresh installs already have header + items from create migration.
        if (! Schema::hasColumn('sales_returns', 'product_id')) {
            $this->ensurePortalChildModule();
            $this->refreshDefaultFilterHeaders();

            return;
        }

        if (! Schema::hasTable('sales_return_items')) {
            Schema::create('sales_return_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->foreignUuid('sales_return_id')->constrained('sales_returns')->cascadeOnDelete();
                $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
                $table->decimal('quantity', 12, 2);
                $table->string('unit')->nullable();
                $table->decimal('pieces', 12, 2)->nullable();
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->decimal('return_value', 12, 2)->default(0);
                $table->timestamps();

                $table->index('sales_return_id');
                $table->index(['organization_id', 'product_id']);
            });
        }

        $returns = DB::table('sales_returns')->get();
        foreach ($returns as $return) {
            if (empty($return->product_id)) {
                continue;
            }

            $exists = DB::table('sales_return_items')
                ->where('sales_return_id', $return->id)
                ->exists();
            if ($exists) {
                continue;
            }

            $unit = DB::table('products')->where('id', $return->product_id)->value('unit');

            DB::table('sales_return_items')->insert([
                'id' => (string) Str::uuid(),
                'organization_id' => $return->organization_id,
                'sales_return_id' => $return->id,
                'product_id' => $return->product_id,
                'quantity' => $return->quantity,
                'unit' => $unit,
                'pieces' => null,
                'unit_price' => $return->unit_price ?? 0,
                'return_value' => $return->return_value ?? 0,
                'created_at' => $return->created_at ?? now(),
                'updated_at' => $return->updated_at ?? now(),
            ]);
        }

        if (! Schema::hasColumn('sales_returns', 'return_number')) {
            Schema::table('sales_returns', function (Blueprint $table) {
                $table->string('return_number')->nullable();
            });
        }
        if (! Schema::hasColumn('sales_returns', 'total_return_value')) {
            Schema::table('sales_returns', function (Blueprint $table) {
                $table->decimal('total_return_value', 12, 2)->default(0);
            });
        }

        foreach ($returns as $index => $return) {
            $date = $return->return_date
                ? date('Ymd', strtotime((string) $return->return_date))
                : date('Ymd');
            $seq = str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);

            DB::table('sales_returns')->where('id', $return->id)->update([
                'return_number' => $return->return_number ?: ('RET-' . $date . '-' . $seq),
                'total_return_value' => $return->total_return_value
                    ?? $return->return_value
                    ?? 0,
            ]);
        }

        // Rebuild header table without legacy product columns (SQLite-safe).
        Schema::create('sales_returns_new', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('return_number')->unique();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->date('return_date');
            $table->decimal('total_return_value', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable()->index();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['organization_id', 'branch_id', 'return_date']);
        });

        $rows = DB::table('sales_returns')->get();
        foreach ($rows as $row) {
            DB::table('sales_returns_new')->insert([
                'id' => $row->id,
                'organization_id' => $row->organization_id,
                'return_number' => $row->return_number,
                'branch_id' => $row->branch_id,
                'return_date' => $row->return_date,
                'total_return_value' => $row->total_return_value ?? 0,
                'notes' => $row->notes,
                'created_by' => $row->created_by,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::disableForeignKeyConstraints();
        Schema::drop('sales_returns');
        Schema::rename('sales_returns_new', 'sales_returns');
        Schema::enableForeignKeyConstraints();

        $this->ensurePortalChildModule();
        $this->refreshDefaultFilterHeaders();
    }

    public function down(): void
    {
        // Irreversible for production data safety; keep items table if present.
    }

    private function ensurePortalChildModule(): void
    {
        $parentId = DB::table('portal_module')->where('modulename', 'SalesReturn')->value('id');
        if (! $parentId) {
            return;
        }

        $exists = DB::table('portal_module')->where('modulename', 'SalesReturnItem')->exists();
        if ($exists) {
            return;
        }

        DB::table('portal_module')->insert([
            'id' => (string) Str::uuid(),
            'modulename' => 'SalesReturnItem',
            'modulelabel' => 'Return Item',
            'is_entity' => 1,
            'is_email' => 0,
            'is_phone' => 0,
            'status' => 'Active',
            'sort_order' => 6,
            'account_id' => 'all',
            'is_system_default' => 1,
            'parent_module_id' => $parentId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function refreshDefaultFilterHeaders(): void
    {
        $defaultHeaders = json_encode([
            ['fieldname' => 'returnNumber', 'fieldlabel' => 'Return #'],
            ['fieldname' => 'branchId', 'fieldlabel' => 'Branch'],
            ['fieldname' => 'returnDate', 'fieldlabel' => 'Return Date'],
            ['fieldname' => 'totalReturnValue', 'fieldlabel' => 'Total Loss'],
            ['fieldname' => 'itemCount', 'fieldlabel' => 'Items'],
            ['fieldname' => 'notes', 'fieldlabel' => 'Notes'],
        ]);

        DB::table('saved-filters')
            ->where('module', 'sales_returns')
            ->where('is_default', true)
            ->update([
                'header_details' => $defaultHeaders,
                'updated_at' => now(),
            ]);
    }
};
