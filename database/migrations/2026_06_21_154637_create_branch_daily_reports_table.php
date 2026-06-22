<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_daily_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->date('report_date');
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->decimal('total_waste_amount', 12, 2)->default(0);
            $table->string('status')->default('submitted');
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // A branch can only submit one report per day
            $table->unique(['branch_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_daily_reports');
    }
};
