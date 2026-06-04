<?php

use App\Modules\Api\V1\Organization\Controllers\OrganizationController;
use App\Modules\Api\V1\User\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Organization endpoints
    Route::prefix('organization')->group(function () {
        Route::get('search', [OrganizationController::class, 'search']);
        Route::post('new', [OrganizationController::class, 'store']);
        Route::get('{id}', [OrganizationController::class, 'show']);
        Route::put('{id}', [OrganizationController::class, 'update']);
        Route::delete('{id}', [OrganizationController::class, 'destroy']);
    });

    // User endpoints under settings
    Route::prefix('settings/User')->group(function () {
        Route::get('', [UserController::class, 'index']);
        Route::post('new', [UserController::class, 'store']);
        Route::get('{id}', [UserController::class, 'show']);
        Route::put('{id}', [UserController::class, 'update']);
        Route::delete('{id}', [UserController::class, 'destroy']);
    });
});
