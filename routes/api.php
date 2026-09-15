<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth/google')->group(function () {
        Route::get('redirect', [GoogleAuthController::class, 'redirectToGoogle']);
        Route::get('callback', [GoogleAuthController::class, 'handleGoogleCallback']);
    }); 

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [GoogleAuthController::class, 'me']);
        Route::post('auth/logout', [GoogleAuthController::class, 'logout']);
    });
});