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
        Schema::create('production_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->date('plan_date');
            $table->string('status')->default('draft'); // draft | approved | completed | cancelled
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable()->index();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['organization_id', 'plan_date']);
        });

        Schema::create('production_plan_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('production_plan_id')->constrained('production_plans')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('planned_quantity', 12, 2);
            $table->decimal('produced_quantity', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['production_plan_id', 'product_id']);
        });

        $exists = DB::table('saved-filters')
            ->where('module', 'production_plans')
            ->where('is_default', true)
            ->whereNull('organization_id')
            ->exists();

        if (!$exists) {
            DB::table('saved-filters')->insert([
                'id' => Str::uuid()->toString(),
                'organization_id' => null,
                'user_id' => null,
                'name' => 'All',
                'module' => 'production_plans',
                'rules' => json_encode([]),
                'is_public' => true,
                'is_default' => true,
                'header_details' => json_encode([
                    ['fieldname' => 'id', 'fieldlabel' => 'ID'],
                    ['fieldname' => 'planDate', 'fieldlabel' => 'Plan Date'],
                    ['fieldname' => 'status', 'fieldlabel' => 'Status'],
                    ['fieldname' => 'notes', 'fieldlabel' => 'Notes'],
                    ['fieldname' => 'createdBy', 'fieldlabel' => 'Created By'],
                    ['fieldname' => 'createdAt', 'fieldlabel' => 'Created At'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('saved-filters')
            ->where('module', 'production_plans')
            ->where('is_default', true)
            ->whereNull('organization_id')
            ->delete();

        Schema::dropIfExists('production_plan_items');
        Schema::dropIfExists('production_plans');
    }
};
