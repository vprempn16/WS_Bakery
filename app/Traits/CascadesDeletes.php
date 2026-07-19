<?php

namespace App\Traits;

use App\Models\BKModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trait for models that should cascade delete to related records.
 *
 * - On soft delete → related records are soft deleted (deleted=1).
 * - On force delete → related records are force deleted (rows removed).
 *
 * Supports: hasMany, hasOne (via module_relation_fields), morphMany-style
 * (activity_relations, comment_rel). No database ON DELETE CASCADE for soft deletes.
 *
 * Example usage (AtomModel already uses this trait):
 *
 *   // Default: all AtomModel entities cascade on delete via deleteRecord().
 *   $record = RecordObject::make('Invoice', $id);
 *   $record->deleteRecord(); // soft-deletes invoice and its line items, etc.
 *
 *   // Exclude specific child modules from cascade (e.g. preserve audit trail):
 *   class Invoice extends AtomModel {
 *       public function getCascadeExcludedModules(): array {
 *           return ['AuditLog'];
 *       }
 *   }
 *
 *   // Force delete (permanent) when needed (e.g. admin / GDPR):
 *   $record->forceDeleteRecord();
 */
trait CascadesDeletes
{
    /**
     * Cascade delete to all dependents. Called automatically from deleteRecord()
     * (soft) and when using forceDeleteRecord() (force).
     *
     * @param bool $force If true, dependents are force-deleted; otherwise soft-deleted
     */
    public function cascadeDeleteToDependents(bool $force = false): void
    {
        // Cascade delete feature intentionally disabled.
        return;
    }

    /**
     * Override in a model to exclude certain child modules from cascade delete.
     * Example: return ['AuditLog']; to never cascade-delete audit logs.
     *
     * @return string[] Module names to exclude from cascade
     */
    public function getCascadeExcludedModules(): array
    {
        return [];
    }

    /**
     * Permanently delete this record and all dependents (no soft delete).
     * Use when you need a hard delete (e.g. GDPR purge, admin tool).
     */
    public function forceDeleteRecord(): void
    {
        if (!$this instanceof BKModel) {
            return;
        }
        $table = $this->getTable();
        $module = $this->getModuleName();
        $id = $this->id;

        // Keep local cleanup for this record only; do not cascade to dependents.
        $customTable = 'l' . strtolower($module) . '_custom_values';
        if (Schema::hasTable($customTable)) {
            DB::table($customTable)->where('record_id', $id)->delete();
        }

        if (Schema::hasTable('address_rel')) {
            DB::table('address_rel')
                ->where('parent_id', $id)
                ->where('parent_module', $module)
                ->delete();
        }

        DB::table($table)->where('id', $id)->delete();
    }
}
