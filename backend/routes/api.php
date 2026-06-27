<?php

use App\Http\Controllers\Api\CommentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Ticket Comments
    Route::get('tickets/{ticket}/comments', [CommentController::class, 'index'])
        ->name('tickets.comments.index');

    Route::post('tickets/{ticket}/comments', [CommentController::class, 'store'])
        ->name('tickets.comments.store');
});
