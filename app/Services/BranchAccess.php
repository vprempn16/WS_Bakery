<?php

namespace App\Services;

use App\Modules\Api\V1\User\Models\User;

class BranchAccess
{
    /**
     * Whether the user may operate on the given branch.
     * Full admins may use any org branch; others only their assigned branch.
     */
    public static function canAccessBranch(?User $user, string $branchId): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->isFullAdmin()) {
            return true;
        }

        return (string) ($user->branch_id ?? '') === (string) $branchId;
    }

    /**
     * @throws \RuntimeException
     */
    public static function assertCanAccessBranch(?User $user, string $branchId): void
    {
        if (!self::canAccessBranch($user, $branchId)) {
            throw new \RuntimeException('You are not allowed to access this branch.');
        }
    }
}
