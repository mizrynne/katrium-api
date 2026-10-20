<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\Auth\GoogleAuthController;
use Spatie\Permission\Middleware\PermissionMiddleware;
use App\Http\Controllers\Api\V1\AccessCatalogController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\PropertyController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\GuestController;
use App\Http\Controllers\Api\V1\GuestViolationController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\Beds24WebhookController;

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

        Route::controller(PropertyController::class)->prefix('properties')->group(function () {
            Route::get('/', 'index')->middleware(PermissionMiddleware::using('view:units'));
            Route::post('/', 'store')->middleware(PermissionMiddleware::using('manage:property'));
            Route::get('{property}', 'show')->whereNumber('property')->middleware(PermissionMiddleware::using('view:units'));
            Route::match(['put', 'patch'], '{property}', 'update')->whereNumber('property')->middleware(PermissionMiddleware::using('manage:property'));
            Route::delete('{property}', 'destroy')->whereNumber('property')->middleware(PermissionMiddleware::using('manage:property'));
        });

        Route::controller(UnitController::class)->prefix('units')->group(function () {
            Route::get('/', 'index')->middleware(PermissionMiddleware::using('view:units'));
            Route::post('/', 'store')->middleware(PermissionMiddleware::using('manage:units'));
            Route::get('{unit}', 'show')->whereNumber('unit')->middleware(PermissionMiddleware::using('view:units'));
            Route::match(['put', 'patch'], '{unit}', 'update')->whereNumber('unit')->middleware(PermissionMiddleware::using('manage:units'));
            Route::delete('{unit}', 'destroy')->whereNumber('unit')->middleware(PermissionMiddleware::using('manage:units'));
        });

        Route::controller(GuestController::class)->prefix('guests')->group(function (): void {
            Route::get('/', 'index')->middleware(PermissionMiddleware::using('manage:guests|view:bookings'));
            Route::get('{guest}', 'show')->whereNumber('guest')->middleware(PermissionMiddleware::using('manage:guests|view:bookings'));
            Route::post('/', 'store')->middleware(PermissionMiddleware::using('manage:guests'));
            Route::match(['put', 'patch'], '{guest}', 'update')->whereNumber('guest')->middleware(PermissionMiddleware::using('manage:guests'));
        });

        Route::controller(GuestViolationController::class)->prefix('guests')->group(function (): void {
            Route::get('{guest}/violations', 'index')->whereNumber('guest')->middleware(PermissionMiddleware::using('manage:guests'));
            Route::post('{guest}/violations', 'store')->whereNumber('guest')->middleware(PermissionMiddleware::using('manage:guests'));
        });

        Route::controller(BookingController::class)->prefix('bookings')->group(function () {
            Route::get('/', 'index')->middleware(PermissionMiddleware::using('view:bookings|manage:bookings'));
            Route::post('/', 'store')->middleware(PermissionMiddleware::using('manage:bookings'));
            Route::get('{booking}', 'show')->whereNumber('booking')->middleware(PermissionMiddleware::using('view:bookings|manage:bookings'));
            Route::match(['put', 'patch'], '{booking}', 'update')->whereNumber('booking')->middleware(PermissionMiddleware::using('manage:bookings'));
            Route::delete('{booking}', 'destroy')->whereNumber('booking')->middleware(PermissionMiddleware::using('manage:bookings'));
        });
    });

    Route::post('webhooks/beds24', Beds24WebhookController::class)->name('webhooks.beds24');

    Broadcast::routes(['middleware' => ['auth:sanctum']]); 
});