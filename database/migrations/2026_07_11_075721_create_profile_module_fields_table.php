<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('profile_module_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profileid');
            $table->string('modulename');
            $table->char('field_id', 36);
            $table->uuid('organization_id')->nullable();
            $table->tinyInteger('invisible')->default(1);
            $table->tinyInteger('editable')->default(0);
            $table->tinyInteger('readonly')->default(0);
            $table->timestamps();
            
            $table->unique(['profileid', 'modulename', 'field_id'], 'profile_module_fields_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profile_module_fields');
    }
};
