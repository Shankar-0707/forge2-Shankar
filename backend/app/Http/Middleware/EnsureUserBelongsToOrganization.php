<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarantees every protected-route request has a user attached to an organization.
 * Users without an organization are blocked before any controller logic runs.
 */
class EnsureUserBelongsToOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $user->organization_id) {
            return response()->json([
                'message' => 'Account is not associated with an organization.',
            ], 403);
        }

        return $next($request);
    }
}
