<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_module', function (Blueprint $table) {
            $table->char('id', 36)->nullable(false);
            $table->primary('id');
            $table->string('modulename', 100)->nullable(false);
            $table->string('modulelabel', 150)->nullable(false);
            $table->tinyInteger('is_entity')->nullable()->default(1);
            $table->tinyInteger('is_email')->nullable()->default(0);
            $table->tinyInteger('is_phone')->nullable()->default(0);
            $table->string('status', 20)->nullable()->default('Active');
            $table->integer('sort_order')->nullable()->default(0);
            $table->string('account_id', 50)->nullable()->default('all');
            $table->tinyInteger('is_system_default')->nullable()->default(1);
            $table->char('parent_module_id', 36)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['modulename'], 'idx_portal_module_modulename');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_module');
    }
};
