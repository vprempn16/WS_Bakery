<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckOrganizationContext
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $userOrgId = (string) $user->organization_id;

            // Body organization context
            $bodyOrgId = $request->input('data.values.organizationId')
                ?? $request->input('data.values.organization_id')
                ?? $request->input('organizationId')
                ?? $request->input('organization_id');

            if ($bodyOrgId && (string) $bodyOrgId !== $userOrgId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized organization context.',
                ], 403);
            }

            // Query organization context
            $queryOrgId = $request->query('organizationId') ?? $request->query('organization_id');
            if ($queryOrgId && (string) $queryOrgId !== $userOrgId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized organization context.',
                ], 403);
            }

            // Header organization context (frontend sends X-Org-Id)
            $headerOrgId = $request->header('X-Org-Id') ?? $request->header('X-Organization-Id');
            if ($headerOrgId && (string) $headerOrgId !== $userOrgId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized organization context.',
                ], 403);
            }

            // Direct Organization routes (PascalCase and lowercase)
            if (
                ($request->is('api/v1/Organization/*') || $request->is('api/v1/organization/*'))
                && ! $request->is('api/v1/Organization/search')
                && ! $request->is('api/v1/organization/search')
                && ! $request->is('api/v1/Organization/new')
                && ! $request->is('api/v1/organization/new')
            ) {
                $routeOrgId = $request->route('id');
                if ($routeOrgId && (string) $routeOrgId !== $userOrgId) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Unauthorized organization context.',
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}
