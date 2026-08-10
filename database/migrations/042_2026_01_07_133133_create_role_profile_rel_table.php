<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_profile_rel', function (Blueprint $table) {
            // Use id() so SQLite does not get a duplicate PRIMARY KEY
            $table->id();
            $table->bigInteger('role_id')->nullable();
            $table->char('organization_id', 36)->nullable();
            $table->integer('profile_id')->nullable();
            $table->index(['role_id'], 'role_profile_rel_role_id_idx');
            $table->index(['organization_id'], 'role_profile_rel_org_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_profile_rel');
    }
};
