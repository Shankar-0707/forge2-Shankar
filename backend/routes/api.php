<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All organization-scoped routes require Sanctum authentication. The
| tenant context is always derived from auth()->user()->organization_id
| — never from request input or URL parameters.
|
*/

// ── Public Auth Routes ───────────────────────────────────────────

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('guest')
        ->name('login');

    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('guest')
        ->name('register');
});

// ── Tenant-Scoped Routes (auth:sanctum) ──────────────────────────

Route::middleware(['auth:sanctum'])->name('api.')->group(function (): void {

    // Auth — current user session
    Route::prefix('auth')->group(function (): void {
        Route::get('me', [AuthenticatedSessionController::class, 'show'])
            ->name('auth.me');

        Route::delete('logout', [AuthenticatedSessionController::class, 'destroy'])
            ->name('auth.logout');
    });

    // Projects — full CRUD resource, organization-scoped via controller
    Route::apiResource('projects', ProjectController::class)
        ->names([
            'index' => 'api.projects.index',
            'store' => 'api.projects.store',
            'show' => 'api.projects.show',
            'update' => 'api.projects.update',
            'destroy' => 'api.projects.destroy',
        ]);
});
