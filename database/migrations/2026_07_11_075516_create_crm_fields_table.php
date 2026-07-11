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
        Schema::create('crm_fields', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('modulename', 255);
            $table->string('fieldname', 255);
            $table->string('fieldlabel', 255);
            $table->string('fieldtype', 255);
            $table->string('tablename', 255);
            $table->tinyInteger('mandatory')->default(0);
            $table->string('apifieldname', 255)->default('');
            $table->integer('displaytype')->default(1);
            $table->tinyInteger('is_custom_field')->default(0);
            $table->uuid('organization_id')->nullable();
            $table->integer('deleted')->default(0);
            $table->integer('seq')->default(0);
            $table->timestamps();
            
            $table->index(['organization_id', 'modulename', 'deleted']);
            $table->index(['organization_id', 'modulename', 'seq']);
        });
    }

    /**
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_fields');
    }
};
