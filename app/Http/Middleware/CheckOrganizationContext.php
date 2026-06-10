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
            $userOrgId = $user->organization_id;

            // Check organizationId in request body (e.g. data.values.organizationId)
            $bodyOrgId = $request->input('data.values.organizationId') 
                ?? $request->input('data.values.organization_id')
                ?? $request->input('organizationId')
                ?? $request->input('organization_id');

            if ($bodyOrgId && $bodyOrgId !== $userOrgId) {
                return response()->json([
                    'message' => 'Unauthorized organization context.'
                ], 403);
            }

            // Check organizationId in query params
            $queryOrgId = $request->query('organizationId') ?? $request->query('organization_id');
            if ($queryOrgId && $queryOrgId !== $userOrgId) {
                return response()->json([
                    'message' => 'Unauthorized organization context.'
                ], 403);
            }

            // Check if viewing/updating/deleting organization directly
            if ($request->is('api/v1/organization/*') && !$request->is('api/v1/organization/search')) {
                $routeOrgId = $request->route('id');
                if ($routeOrgId && $routeOrgId !== $userOrgId) {
                    return response()->json([
                        'message' => 'Unauthorized organization context.'
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}

