<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\Auth\GoogleAuthController;
use Spatie\Permission\Middleware\PermissionMiddleware;
use App\Http\Controllers\Api\V1\AccessCatalogController;
use App\Http\Controllers\Api\V1\UserController;

Route::prefix('v1')->group(function () {
    Route::prefix('auth/google')->group(function () {
        Route::get('redirect', [GoogleAuthController::class, 'redirectToGoogle']);
        Route::get('callback', [GoogleAuthController::class, 'handleGoogleCallback']);
    }); 

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [GoogleAuthController::class, 'me']);
        Route::post('auth/logout', [GoogleAuthController::class, 'logout']);

        Route::get('access-catalog', AccessCatalogController::class)->middleware(PermissionMiddleware::using('manage:users'));
        Route::apiResource('users', UserController::class)->middleware(PermissionMiddleware::using('manage:users'));
    });

    Broadcast::routes(['middleware' => ['auth:sanctum']]); 
});