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
        Schema::create('crm_default_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->uuid('organization_id');
            $table->string('modulename');
            $table->string('fieldname');
            $table->string('fieldlabel');
            $table->tinyInteger('mandatory')->default(0);
            $table->integer('seq')->default(0);
            $table->timestamps();
            
            $table->unique(['organization_id', 'modulename', 'fieldname'], 'crm_default_field_def_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_default_field_definitions');
    }
};
