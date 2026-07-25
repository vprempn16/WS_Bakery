<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $actions = [
            ['id' => 1, 'action_key' => 'view', 'label' => 'View', 'security_check' => 1],
            ['id' => 2, 'action_key' => 'create', 'label' => 'Create', 'security_check' => 1],
            ['id' => 3, 'action_key' => 'edit', 'label' => 'Edit', 'security_check' => 1],
            ['id' => 4, 'action_key' => 'delete', 'label' => 'Delete', 'security_check' => 1],
        ];

        foreach ($actions as $action) {
            $exists = DB::table('system_actions')->where('id', $action['id'])->exists();
            if ($exists) {
                continue;
            }
            DB::table('system_actions')->insert([
                ...$action,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('system_actions')->whereIn('action_key', ['view', 'create', 'edit', 'delete'])->delete();
    }
};
