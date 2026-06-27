<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProjectController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('register', RegisterController::class);
    Route::post('login', LoginController::class);
});

/*
|--------------------------------------------------------------------------
| Protected API Routes (Sanctum Authenticated)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Auth verification & session endpoints
    Route::prefix('auth')->group(function () {
        Route::post('logout', LogoutController::class);
        Route::get('me', function (Request $request) {
            $user = $request->user();
            return response()->json([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'organization_id' => $user->organization_id,
            ]);
        });
    });

    // Ticket CRUD Resource & Nested Comments CRUD
    Route::apiResource('tickets', TicketController::class);
    Route::apiResource('tickets.comments', CommentController::class)->shallow();

    // Custom Ticket Action endpoints
    Route::prefix('tickets/{ticket}')->group(function () {
        Route::post('claim', [TicketController::class, 'claim']);
        Route::post('reassign', [TicketController::class, 'reassign']);
    });

    // Support resources for reassign and user directory dropdowns
    Route::get('users/assignable', [TicketController::class, 'assignableUsers']);

    // Admin & Organization CRUD
    Route::apiResource('organizations', OrganizationController::class);
    Route::apiResource('users', UserController::class);
    Route::apiResource('projects', ProjectController::class);

    // Dashboard metrics
    Route::get('stats/metrics', [StatsController::class, 'metrics']);
});
