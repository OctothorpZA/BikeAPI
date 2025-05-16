<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Http\Controllers\SocialiteLoginController; // Staff SSO
use App\Http\Controllers\Api\V1\PwaSocialiteController; // PWA SSO <-- NEW IMPORT

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Route 1 (Original simpler dashboard route)
// Route::view('dashboard', 'dashboard')
//     ->middleware(['auth', 'verified'])
//     ->name('dashboard.simple'); // Renamed to avoid conflict

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');
    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

// --- Staff Socialite SSO Routes ---
Route::prefix('auth/staff')->name('auth.staff.social.')->group(function () { // Added 'staff' to prefix and name
    Route::get('/{provider}/redirect', [SocialiteLoginController::class, 'redirectToProvider'])
        ->where('provider', '[a-zA-Z0-9_-]+')
        ->name('redirect');
    Route::get('/{provider}/callback', [SocialiteLoginController::class, 'handleProviderCallback'])
        ->where('provider', '[a-zA-Z0-9_-]+')
        ->name('callback');
});

// --- PWA User Socialite SSO Routes (Now in web.php for session support) ---
Route::prefix('auth/pwa')->name('auth.pwa.social.')->group(function () { // New prefix for PWA
    Route::get('/{provider}/redirect', [PwaSocialiteController::class, 'redirectToProvider'])
        ->where('provider', '[a-zA-Z0-9_-]+')
        ->name('redirect'); // e.g., route('auth.pwa.social.redirect', 'google')

    Route::get('/{provider}/callback', [PwaSocialiteController::class, 'handleProviderCallback'])
        ->where('provider', '[a-zA-Z0-9_-]+')
        ->name('callback'); // e.g., route('auth.pwa.social.callback', 'google')
});


require __DIR__.'/auth.php'; // Fortify routes

// Route 2 (Jetstream's default dashboard route group)
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});
