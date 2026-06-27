<?php

declare(strict_types=1);

use App\Http\Controllers\ApiController;
use App\Http\Middleware\EnsureUserBelongsToOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function (): void {
    // Health check — no auth.
    Route::get('/health', fn () response()->json([
        'status'  => 'ok',
        'service' => 'pulsedesk-api',
        'version' => app()->version(),
        'time'    => now()->toIso8601String(),
    ]));
});

/*
|--------------------------------------------------------------------------
| Protected (Tenant-Scoped) API Routes
|--------------------------------------------------------------------------
| Every route here is:
|   1. Sanctum-authenticated (auth:sanctum)
|   2. Tenant-gated (EnsureUserBelongsToOrganization)
|   3. Rate-limited (60/min per user via 'api' throttle config)
*/

Route::middleware([
    'auth:sanctum',
    EnsureUserBelongsToOrganization::class,
    'throttle:api',
])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        // Currently authenticated user / organization context.
        Route::get('/me', function (Request $request) {
            $user = $request->user();
            $org  = $user->organization;

            return response()->json([
                'user' => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                ],
                'organization' => $org ? [
                    'id'   => $org->id,
                    'name' => $org->name,
                    'slug' => $org->slug ?? null,
                ] : null,
            ]);
        })->name('me');

        // Feature controllers will be registered here, e.g.:
        // Route::apiResource('tickets', TicketController::class);
        // Route::apiResource('customers', CustomerController::class);
    });
