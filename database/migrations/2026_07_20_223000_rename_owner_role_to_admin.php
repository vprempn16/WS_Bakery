<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Rename legacy bakery "owner" to org "admin".
        DB::table('users')->where('role', 'owner')->update(['role' => 'admin']);
    }

    public function down(): void
    {
        // Irreversible safely — do not demote all admins back to owner.
    }
};
