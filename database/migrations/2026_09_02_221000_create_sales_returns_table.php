<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $table) {
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

    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
        Schema::dropIfExists('sales_returns');
    }
};
