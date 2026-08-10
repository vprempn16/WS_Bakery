<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict settings island (User / Profile / Role / fields) to org admins.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !method_exists($user, 'isFullAdmin') || !$user->isFullAdmin()) {
            return response()->json([
                'status' => false,
                'message' => 'Admin access required.',
            ], 403);
        }

        return $next($request);
    }
}
