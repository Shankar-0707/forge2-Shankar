<?php

use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    // Ticket actions
    Route::prefix('tickets')->group(function () {
        Route::get('/{ticket}', [TicketController::class, 'show']);
        Route::post('/{ticket}/claim', [TicketController::class, 'claim']);
        Route::post('/{ticket}/reassign', [TicketController::class, 'reassign']);
    });

    // Assignable users (for reassign dropdown)
    Route::get('/users/assignable', [TicketController::class, 'assignableUsers']);

    // Stats / Metrics
    Route::get('/stats/metrics', [StatsController::class, 'metrics']);
});
