<?php

namespace App\Modules\Api\V1\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\User\Models\User;
use App\Modules\Api\V1\User\Requests\LoginRequest;
use App\Modules\Api\V1\User\Requests\ChangePasswordRequest;
use App\Services\AuthSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    private const LOGIN_MAX_ATTEMPTS = 5;

    private const LOGIN_DECAY_SECONDS = 60;

    private const ACCOUNT_LOCK_ATTEMPTS = 10;

    private const ACCOUNT_LOCK_SECONDS = 900;

    public function login(LoginRequest $request)
    {
        $values = $request->input('data.values');
        $email = strtolower(trim((string) ($values['email'] ?? '')));
        $ip = (string) $request->ip();

        $ipKey = 'login:ip:' . $ip;
        $accountKey = 'login:account:' . $email;

        if (RateLimiter::tooManyAttempts($accountKey, self::ACCOUNT_LOCK_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($accountKey);
            Log::warning('Login blocked — account lockout', ['email' => $email, 'ip' => $ip]);

            return $this->error(
                'Too many failed login attempts. Try again in ' . $seconds . ' seconds.',
                null,
                null,
                null,
                429
            );
        }

        if (RateLimiter::tooManyAttempts($ipKey, self::LOGIN_MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($ipKey);

            return $this->error(
                'Too many login attempts. Try again in ' . $seconds . ' seconds.',
                null,
                null,
                null,
                429
            );
        }

        $user = User::with(['organization', 'branch'])
            ->where('email', $values['email'])
            ->first();

        if (! $user || ! Hash::check($values['password'], $user->password)) {
            RateLimiter::hit($ipKey, self::LOGIN_DECAY_SECONDS);
            RateLimiter::hit($accountKey, self::ACCOUNT_LOCK_SECONDS);
            Log::info('Failed login attempt', ['email' => $email, 'ip' => $ip]);

            return $this->error('Invalid email or password.', null, null, null, 401);
        }

        if ((int) ($user->is_active ?? 1) !== 1) {
            return $this->error('Your account is inactive. Contact your organization admin.', null, null, null, 403);
        }

        RateLimiter::clear($ipKey);
        RateLimiter::clear($accountKey);

        $useSession = AuthSessionService::prefersCookieSession($request);

        if ($useSession) {
            AuthSessionService::loginUser($request, $user);
            $payload = AuthSessionService::authPayload($user);
        } else {
            // Non-browser API clients (tests, automation) may still request a bearer token.
            $token = $user->createToken('auth_token')->plainTextToken;
            $payload = AuthSessionService::authPayload($user);
            $payload['token'] = $token;
        }

        Log::info('Successful login', [
            'user_id' => $user->id,
            'org_id' => $user->organization_id,
            'ip' => $ip,
            'session' => $useSession,
        ]);

        return $this->success($payload, 'Login successful.');
    }

    /**
     * Return the authenticated user (session cookie or bearer token).
     */
    public function me(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Unauthenticated.', null, null, null, 401);
        }

        return $this->success(AuthSessionService::authPayload($user), 'Authenticated.');
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            AuthSessionService::logoutUser($request, $user);
        }

        return $this->success(null, 'Logout successful.');
    }

    /**
     * Non-admin (or any logged-in user) changing own password — requires current password.
     * Revokes bearer tokens and regenerates the session.
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();
        $values = $request->input('data.values', []);

        if (! Hash::check((string) ($values['currentPassword'] ?? ''), $user->password)) {
            return $this->error('Current password is incorrect.', null, null, null, 422);
        }

        $user->password = Hash::make((string) $values['password']);
        $user->save();

        $user->tokens()->delete();
        AuthSessionService::loginUser($request, $user->fresh());

        return $this->success(null, 'Password changed successfully.');
    }
}
