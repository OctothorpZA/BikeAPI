<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// --- Import API Controllers ---
use App\Http\Controllers\Api\V1\DepotController;
use App\Http\Controllers\Api\V1\RentalController;
use App\Http\Controllers\Api\V1\PwaAuthController;
// PwaSocialiteController routes are in web.php
use App\Http\Controllers\Api\V1\PwaUserController;
use App\Http\Controllers\Api\V1\GooglePlacesController;
use App\Http\Controllers\Api\V1\PwaConfigController; // Ensure this is imported

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user()->load(['roles', 'permissions', 'currentTeam']);
});

// API Version 1 Routes for PWA
Route::prefix('v1')->name('api.v1.')->group(function () {

    // --- Publicly Accessible Endpoints ---
    Route::get('/public/depots', [DepotController::class, 'publicIndex'])->name('public.depots.index');
    Route::post('/pwa/login', [PwaAuthController::class, 'login'])->name('pwa.login');
    Route::get('/pwa/config', [PwaConfigController::class, 'show'])->name('pwa.config.show'); // This is the route

    Route::post('/rentals/validate-booking', [RentalController::class, 'validateBookingAndIssueToken'])->name('rentals.validatebooking');

    // --- Authenticated Endpoints (require Sanctum token for PWA users) ---
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/depots', [DepotController::class, 'index'])->name('depots.index');
        Route::get('/google-places/nearby', [GooglePlacesController::class, 'nearby'])->name('googleplaces.nearby');
        Route::post('/pwa/register-user', [PwaAuthController::class, 'registerUserFromPwa'])->name('pwa.register');
        Route::post('/pwa/link-booking', [PwaUserController::class, 'linkBooking'])->name('pwa.linkbooking');
        Route::get('/pwa/my-rentals', [PwaUserController::class, 'myRentals'])->name('pwa.myrentals');
        Route::post('/pwa/logout', [PwaAuthController::class, 'logout'])->name('pwa.logout');
    });
});
