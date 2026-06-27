<?php

use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\TicketController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/tickets', [TicketController::class, 'index']);
    Route::get('/agents', [AgentController::class, 'index']);
});
