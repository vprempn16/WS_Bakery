<?php

namespace App\Services;

use App\Modules\Api\V1\Branch\Models\Branch;
use App\Modules\Api\V1\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthSessionService
{
    /**
     * Whether the request should use HttpOnly session cookies (SPA) vs bearer tokens (API clients).
     */
    public static function prefersCookieSession(Request $request): bool
    {
        if ($request->boolean('useToken')) {
            return false;
        }

        $stateful = array_filter(array_map('trim', config('sanctum.stateful', [])));
        if ($stateful === []) {
            return false;
        }

        $origin = (string) ($request->headers->get('Origin') ?? $request->headers->get('Referer') ?? '');
        if ($origin === '') {
            return false;
        }

        $host = parse_url($origin, PHP_URL_HOST);
        $port = parse_url($origin, PHP_URL_PORT);
        $originHost = $host ? ($port ? "{$host}:{$port}" : $host) : '';

        foreach ($stateful as $domain) {
            if ($domain === '' || $domain === $originHost || $domain === $host) {
                return true;
            }
        }

        return false;
    }

    public static function loginUser(Request $request, User $user): void
    {
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
    }

    public static function logoutUser(Request $request, ?User $user = null): void
    {
        if ($user) {
            $user->tokens()->delete();
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function authPayload(User $user): array
    {
        $user->loadMissing(['organization', 'branch']);
        $isFullAdmin = $user->isFullAdmin();

        if ($isFullAdmin) {
            $branchModels = Branch::where('organization_id', $user->organization_id)
                ->orderBy('name')
                ->get();
        } elseif ($user->branch_id) {
            $branchModels = Branch::where('organization_id', $user->organization_id)
                ->where('id', $user->branch_id)
                ->get();
        } else {
            $branchModels = collect();
        }

        $branches = $branchModels->map(fn (Branch $b) => [
            'id' => $b->id,
            'org_id' => $b->organization_id,
            'name' => $b->name,
            'address' => $b->address,
            'phone' => $b->phone,
            'type' => $b->type,
        ])->values()->all();

        $isActive = (int) ($user->is_active ?? 1) === 1 ? 1 : 0;

        return [
            'token' => null,
            'refresh_token' => null,
            'org_id' => $user->organization_id,
            'branches' => $branches,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->email ?? 'User'),
                'email' => $user->email,
                'phone_number' => $user->phone,
                'role' => $user->role,
                'is_admin' => $isFullAdmin,
                'is_active' => $isActive,
                'org_id' => $user->organization_id,
                'branch_id' => $user->branch_id,
                'organization' => $user->organization ? [
                    'id' => $user->organization->id,
                    'name' => $user->organization->name,
                ] : null,
                'allowed_modules' => $user->getAllowedModules(),
            ],
        ];
    }
}
