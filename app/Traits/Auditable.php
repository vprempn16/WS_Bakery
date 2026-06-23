<?php

namespace App\Traits;

use App\Modules\Api\V1\AuditLog\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    public static function bootAuditable()
    {
        static::created(function ($model) {
            $model->logAudit('created');
        });

        static::updated(function ($model) {
            $model->logAudit('updated');
        });

        static::deleted(function ($model) {
            $model->logAudit('deleted');
        });
    }

    protected function logAudit(string $event)
    {
        $userId = Auth::id();
        
        $className = class_basename(static::class);

        $oldValues = [];
        $newValues = [];

        if ($event === 'updated') {
            $oldValues = $this->getOriginal();
            $newValues = $this->getAttributes();
            
            $changes = $this->getChanges();
            
            // Exclude updated_at from being the only change logged
            unset($changes['updated_at']);
            
            if (empty($changes)) {
                return;
            }
            
            $oldValues = array_intersect_key($oldValues, $changes);
            $newValues = array_intersect_key($newValues, $changes);
        } elseif ($event === 'created') {
            $newValues = $this->getAttributes();
        } elseif ($event === 'deleted') {
            $oldValues = $this->getAttributes();
        }

        AuditLog::create([
            'organization_id' => $this->organization_id ?? (Auth::check() ? Auth::user()->organization_id : null),
            'user_id' => $userId,
            'module' => $className,
            'record_id' => $this->id,
            'event' => $event,
            'old_values' => empty($oldValues) ? null : $oldValues,
            'new_values' => empty($newValues) ? null : $newValues,
        ]);
    }
}
