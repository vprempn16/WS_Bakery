<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Seed default "All" column filter for Billing list (column picker).
     */
    public function up(): void
    {
        $exists = DB::table('saved-filters')
            ->where('module', 'billings')
            ->where('is_default', true)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('saved-filters')->insert([
            'id' => Str::uuid()->toString(),
            'organization_id' => null,
            'user_id' => null,
            'name' => 'All',
            'module' => 'billings',
            'rules' => json_encode([]),
            'is_public' => true,
            'is_default' => true,
            'header_details' => json_encode([
                ['fieldname' => 'billNumber', 'fieldlabel' => 'Bill Number'],
                ['fieldname' => 'branchId', 'fieldlabel' => 'Branch'],
                ['fieldname' => 'customerName', 'fieldlabel' => 'Customer Name'],
                ['fieldname' => 'itemCount', 'fieldlabel' => 'Items'],
                ['fieldname' => 'grandTotal', 'fieldlabel' => 'Grand Total'],
                ['fieldname' => 'paymentMethod', 'fieldlabel' => 'Payment Method'],
                ['fieldname' => 'paymentStatus', 'fieldlabel' => 'Payment Status'],
                ['fieldname' => 'billingDate', 'fieldlabel' => 'Billing Date'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('saved-filters')
            ->where('module', 'billings')
            ->where('is_default', true)
            ->whereNull('organization_id')
            ->delete();
    }
};
