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
        if (Schema::hasTable('product_stock_transactions') && ! Schema::hasColumn('product_stock_transactions', 'expiry_date')) {
            Schema::table('product_stock_transactions', function (Blueprint $table) {
                $table->date('expiry_date')->nullable()->after('quantity');
            });
        }

        // Seed 3 Months / 4 Months shelf life picklist values for Product.shelfLife
        $fieldIds = DB::table('crm_fields')
            ->where('modulename', 'products')
            ->where('fieldname', 'shelfLife')
            ->where('deleted', 0)
            ->pluck('id');

        foreach ($fieldIds as $fieldId) {
            foreach ([
                ['value' => '2160', 'label' => '3 Months'],
                ['value' => '2880', 'label' => '4 Months'],
            ] as $i => $opt) {
                $exists = DB::table('picklist_values')
                    ->where('field_id', $fieldId)
                    ->where('value', $opt['value'])
                    ->exists();
                if ($exists) {
                    continue;
                }
                $maxSort = (int) DB::table('picklist_values')->where('field_id', $fieldId)->max('sort_order');
                DB::table('picklist_values')->insert([
                    'id' => (string) Str::uuid(),
                    'field_id' => $fieldId,
                    'label' => $opt['label'],
                    'value' => $opt['value'],
                    'sort_order' => $maxSort + $i + 1,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_stock_transactions') && Schema::hasColumn('product_stock_transactions', 'expiry_date')) {
            Schema::table('product_stock_transactions', function (Blueprint $table) {
                $table->dropColumn('expiry_date');
            });
        }
    }
};
