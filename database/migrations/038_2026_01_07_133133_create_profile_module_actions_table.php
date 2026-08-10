<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_module_actions', function (Blueprint $table) {
            // Use id() so SQLite does not get a duplicate PRIMARY KEY
            $table->id();
            $table->integer('profileid')->nullable(false);
            $table->char('organization_id', 36)->nullable();
            $table->string('modulename', 100)->nullable(false);
            $table->unsignedInteger('action_id')->nullable(false);
            $table->tinyInteger('permission')->nullable(false)->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['profileid', 'modulename', 'action_id'], 'pma_profile_module_action_unique');
            $table->index(['profileid'], 'pma_profileid_idx');
            $table->index(['modulename'], 'pma_modulename_idx');
            $table->index(['action_id'], 'pma_action_fk');
            $table->foreign('action_id', 'pma_action_fk')->references('id')->on('system_actions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_module_actions');
    }
};
