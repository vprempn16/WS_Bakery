<?php

namespace App\Services;

use App\Modules\Api\V1\Branch\Models\Branch;
use App\Modules\Api\V1\User\Models\User;
use Illuminate\Http\Request;

class BranchAccess
{
    /**
     * Whether the user may operate on the given branch.
     * Full admins may use any branch in their organization; others only their assigned branch.
     */
    public static function canAccessBranch(?User $user, string $branchId): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->isFullAdmin()) {
            if ($branchId === '') {
                return false;
            }

            return Branch::where('organization_id', $user->organization_id)
                ->where('id', $branchId)
                ->exists();
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

    /**
     * Resolve active branch from X-Branch-Id header or query (branchId / branch_id).
     * Staff are always locked to their assigned branch when one exists.
     */
    public static function resolveBranchIdFromRequest(Request $request, ?User $user): ?string
    {
        if ($user && ! $user->isFullAdmin()) {
            return $user->branch_id ? (string) $user->branch_id : null;
        }

        $raw = $request->header('X-Branch-Id')
            ?: $request->query('branchId')
            ?: $request->query('branch_id');

        if ($raw === null || $raw === '') {
            return null;
        }

        return (string) $raw;
    }

    /**
     * Apply optional branch scope for admin list endpoints (header or query).
     * Staff without a branch get a RuntimeException (caller should 403).
     *
     * @throws \RuntimeException
     */
    public static function applyListBranchScope($query, Request $request, ?User $user, string $column = 'branch_id')
    {
        if (! $user) {
            throw new \RuntimeException('Unauthenticated.');
        }

        if (! $user->isFullAdmin()) {
            if (! $user->branch_id) {
                throw new \RuntimeException('No branch assigned to this user.');
            }
            $query->where($column, $user->branch_id);

            return $query;
        }

        $branchId = self::resolveBranchIdFromRequest($request, $user);
        if ($branchId) {
            self::assertCanAccessBranch($user, $branchId);
            $query->where($column, $branchId);
        }

        return $query;
    }
}
