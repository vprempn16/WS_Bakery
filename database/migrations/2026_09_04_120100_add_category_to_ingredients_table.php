<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            if (! Schema::hasColumn('ingredients', 'category')) {
                $table->string('category', 30)->default('raw')->after('unit');
                $table->index(['organization_id', 'category']);
            }
        });

        if (Schema::hasColumn('ingredients', 'category')) {
            DB::table('ingredients')
                ->where(function ($q) {
                    $q->whereNull('category')->orWhere('category', '');
                })
                ->update(['category' => 'raw']);
        }
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            if (Schema::hasColumn('ingredients', 'category')) {
                $table->dropIndex(['organization_id', 'category']);
                $table->dropColumn('category');
            }
        });
    }
};
