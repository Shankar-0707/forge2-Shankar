<?php

declare(strict_types=1);

use App\Http\Controllers\V1\DashboardMetricsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard/metrics', [DashboardMetricsController::class, 'index']);
});
