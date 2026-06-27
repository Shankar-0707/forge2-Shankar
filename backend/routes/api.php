<?php

use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Organization — tenant singleton, route-model-bound but org-scoped in controller
    Route::apiResource('organizations', OrganizationController::class)
        ->only(['index', 'show', 'update', 'destroy']);

    // Users — full CRUD, organization_id always from auth user
    Route::apiResource('users', UserController::class);
});
