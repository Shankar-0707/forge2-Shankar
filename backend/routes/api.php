<?php

use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API routes (no tenant guard required)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {
    Route::post('auth/login', [\App\Http\Controllers\Auth\AuthController::class, 'login']);
});

/*
|--------------------------------------------------------------------------
| Tenant-scoped routes
| Every request here is guaranteed to operate only within the
| authenticated user's organization_id.
|--------------------------------------------------------------------------
*/
Route::prefix('v1')
    ->middleware(['auth:sanctum', 'tenant'])
    ->group(function () {
        Route::get('me', fn () => response()->json(auth()->user()));

        Route::apiResource('tickets', TicketController::class);
        Route::apiResource('organizations.tickets', TicketController::class)
            ->shallow();
    });
