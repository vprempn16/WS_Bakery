<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill missing audit fields + add reversal linkage for safe correction.
        if (!Schema::hasTable('inventory_transactions')) {
            return;
        }

        if (!Schema::hasColumn('inventory_transactions', 'created_by')) {
            Schema::table('inventory_transactions', function (Blueprint $table) {
                $table->uuid('created_by')->nullable()->index()->after('reference_note');
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('inventory_transactions', 'reversed_at')) {
            Schema::table('inventory_transactions', function (Blueprint $table) {
                $table->timestamp('reversed_at')->nullable()->index()->after('created_by');
            });
        }

        if (!Schema::hasColumn('inventory_transactions', 'reversed_by')) {
            Schema::table('inventory_transactions', function (Blueprint $table) {
                $table->uuid('reversed_by')->nullable()->index()->after('reversed_at');
                $table->foreign('reversed_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('inventory_transactions', 'reversal_transaction_id')) {
            Schema::table('inventory_transactions', function (Blueprint $table) {
                $table->uuid('reversal_transaction_id')->nullable()->index()->after('reversed_by');
                $table
                    ->foreign('reversal_transaction_id')
                    ->references('id')
                    ->on('inventory_transactions')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('inventory_transactions')) {
            return;
        }

        Schema::table('inventory_transactions', function (Blueprint $table) {
            // Drop FKs first (if they exist), then columns.
            try { $table->dropForeign(['reversal_transaction_id']); } catch (\Throwable $e) {}
            try { $table->dropForeign(['reversed_by']); } catch (\Throwable $e) {}
            try { $table->dropForeign(['created_by']); } catch (\Throwable $e) {}

            if (Schema::hasColumn('inventory_transactions', 'reversal_transaction_id')) {
                $table->dropColumn('reversal_transaction_id');
            }
            if (Schema::hasColumn('inventory_transactions', 'reversed_by')) {
                $table->dropColumn('reversed_by');
            }
            if (Schema::hasColumn('inventory_transactions', 'reversed_at')) {
                $table->dropColumn('reversed_at');
            }
            if (Schema::hasColumn('inventory_transactions', 'created_by')) {
                $table->dropColumn('created_by');
            }
        });
    }
};

