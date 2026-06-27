<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — PulseDesk
|--------------------------------------------------------------------------
|
| All API routes below are automatically prefixed with /api.
| Sanctum-protected routes use the 'auth:sanctum' middleware.
|
*/

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'PulseDesk API',
        'timestamp' => now()->toISOString(),
    ]);
});

// Auth routes (public)
Route::prefix('auth')->group(function () {
    // TODO: register, login, logout, me endpoints in Task #2
});

// Protected routes — require Sanctum token
Route::middleware('auth:sanctum')->group(function () {
    // TODO: organization, ticket, user endpoints in subsequent tasks
});
