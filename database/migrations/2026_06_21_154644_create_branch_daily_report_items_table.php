<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_daily_report_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_daily_report_id')->constrained('branch_daily_reports')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity_sold', 10, 2)->default(0);
            $table->decimal('quantity_returned', 10, 2)->default(0); // This represents waste/unsold
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('subtotal_revenue', 12, 2)->default(0); // quantity_sold * unit_price
            $table->decimal('subtotal_waste', 12, 2)->default(0); // quantity_returned * unit_price
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_daily_report_items');
    }
};
