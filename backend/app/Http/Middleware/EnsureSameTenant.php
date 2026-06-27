<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the authenticated user can only touch resources that
 * belong to their own organization (tenant).
 *
 * Tenancy is resolved exclusively from the authenticated user's
 * organization_id — never from request input. If a client attempts
 * to scope a request to any other organization (via route parameter,
 * query string, or body), the request is aborted with 403.
 */
class EnsureSameTenant
{
    /**
     * Keys that may carry a tenant identifier anywhere in the request.
     * Used purely for detection — values are never trusted.
     */
    protected array $tenantParameterKeys = [
        'organization',
        'organization_id',
        'tenant',
        'tenant_id',
        'org_id',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Defer to Sanctum/auth middleware for unauthenticated requests.
        if (! $user) {
            return $next($request);
        }

        $tenantId = $this->resolveTenantId($user);

        // Users without an organization fall through; resource policies
        // are responsible for deciding whether they may proceed.
        if ($tenantId === null) {
            return $next($request);
        }

        $this->guardRouteParameters($request, $tenantId);
        $this->guardRequestInput($request, $tenantId);

        return $next($request);
    }

    protected function resolveTenantId($user): ?int
    {
        $id = $user->organization_id ?? null;

        return $id === null ? null : (int) $id;
    }

    protected function guardRouteParameters(Request $request, int $tenantId): void
    {
        $route = $request->route();

        if (! $route) {
            return;
        }

        foreach ($this->tenantParameterKeys as $key) {
            $value = $route->parameter($key);

            if ($value === null) {
                continue;
            }

            // Route-model-bound resources expose their tenant via a
            // relationship; scalar bindings expose it directly.
            $resolved = is_object($value)
                ? ($value->organization_id ?? $value->id ?? null)
                : $value;

            if ($resolved === null || $resolved === '') {
                continue;
            }

            if ((int) $resolved !== $tenantId) {
                abort(403, 'Cross-tenant access is forbidden.');
            }
        }
    }

    protected function guardRequestInput(Request $request, int $tenantId): void
    {
        $inputs = Arr::only($request->input(), $this->tenantParameterKeys);

        foreach ($inputs as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if ((int) $value !== $tenantId) {
                abort(
                    403,
                    "Cross-tenant access is forbidden for parameter [{$key}]."
                );
            }
        }
    }
}
