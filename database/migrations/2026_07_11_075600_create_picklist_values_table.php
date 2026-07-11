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
        Schema::create('picklist_values', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('field_id', 36);
            $table->string('label');
            $table->string('value');
            $table->integer('sort_order')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
            
            $table->index('field_id');
        });
    }

    /**
     */
    public function down(): void
    {
        Schema::dropIfExists('picklist_values');
    }
};
