<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_issues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('issue_number')->unique();
            $table->date('issue_date');
            $table->string('status')->default('posted'); // posted | cancelled
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable()->index();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['organization_id', 'issue_date']);
        });

        Schema::create('material_issue_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('material_issue_id')->constrained('material_issues')->cascadeOnDelete();
            $table->foreignUuid('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->string('unit')->nullable();
            $table->timestamps();

            $table->unique(['material_issue_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_issue_items');
        Schema::dropIfExists('material_issues');
    }
};
