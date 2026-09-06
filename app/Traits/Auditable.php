<?php

namespace App\Traits;

use App\Modules\Api\V1\AuditLog\Models\AuditLog;
use App\Services\AuthUser;
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
        // Prefer AuthUser (app User model) so admin CRUD always attributes the actor.
        $userId = AuthUser::id() ?? Auth::id();

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
            unset($newValues['password'], $newValues['remember_token']);
        } elseif ($event === 'deleted') {
            $oldValues = $this->getAttributes();
            unset($oldValues['password'], $oldValues['remember_token']);
        }

        // Never persist password hashes in audit payloads
        unset($oldValues['password'], $oldValues['remember_token'], $newValues['password'], $newValues['remember_token']);

        AuditLog::create([
            'organization_id' => $this->organization_id ?? AuthUser::organizationId() ?? (Auth::check() ? Auth::user()->organization_id : null),
            'user_id' => $userId,
            'module' => $className,
            'record_id' => $this->id,
            'event' => $event,
            'old_values' => empty($oldValues) ? null : $oldValues,
            'new_values' => empty($newValues) ? null : $newValues,
        ]);
    }
}
