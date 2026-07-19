<?php

namespace App\Services;

use App\Modules\Api\V1\User\Models\User;
use Illuminate\Support\Facades\Auth;

class AuthUser
{
    public static function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    public static function id(): ?string
    {
        return self::user()?->id;
    }

    public static function organizationId(): ?string
    {
        return self::user()?->organization_id;
    }

    public static function get(): ?User
    {
        return self::user();
    }

    public static function requireUser(): User
    {
        $user = self::user();
        if (!$user) {
            throw new \RuntimeException('Authentication required.');
        }

        return $user;
    }
}
