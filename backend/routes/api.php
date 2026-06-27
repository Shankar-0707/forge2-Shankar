<?php

use App\Http\Controllers\V1\TicketActivityController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {

    // ── Activity / Audit Trail ──────────────────────────────────────
    Route::get('tickets/{ticket}/activity', [TicketActivityController::class, 'index'])
        ->name('v1.tickets.activity.index');

    // ... existing routes (tickets CRUD, comments, etc.)

});
