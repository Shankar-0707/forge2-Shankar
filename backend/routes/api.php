<?php

use App\Http\Controllers\V1\TicketAssignmentController;
use App\Http\Controllers\V1\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('v1')->name('api.v1.')->group(function () {

    // Tickets — CRUD
    Route::apiResource('tickets', TicketController::class);

    // Ticket Assignment
    Route::post('tickets/{ticket}/assign', [TicketAssignmentController::class, 'assign'])
        ->name('tickets.assign');

    Route::post('tickets/{ticket}/claim', [TicketAssignmentController::class, 'claim'])
        ->name('tickets.claim');
});
