<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt; // Make sure Volt is imported
use App\Http\Controllers\SocialiteLoginController; // Staff SSO
use App\Http\Controllers\Api\V1\PwaSocialiteController; // PWA SSO
use App\Livewire\Staff\Dashboard as StaffDashboard;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');
    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

// --- Staff Socialite SSO Routes ---
Route::prefix('auth/staff')->name('auth.staff.social.')->group(function () {
    Route::get('/{provider}/redirect', [SocialiteLoginController::class, 'redirectToProvider'])
        ->where('provider', '[a-zA-Z0-9_-]+')
        ->name('redirect');
    Route::get('/{provider}/callback', [SocialiteLoginController::class, 'handleProviderCallback'])
        ->where('provider', '[a-zA-Z0-9_-]+')
        ->name('callback');
});

// --- PWA User Socialite SSO Routes ---
Route::prefix('auth/pwa')->name('auth.pwa.social.')->group(function () {
    Route::get('/{provider}/redirect', [PwaSocialiteController::class, 'redirectToProvider'])
        ->where('provider', '[a-zA-Z0-9_-]+')
        ->name('redirect');
    Route::get('/{provider}/callback', [PwaSocialiteController::class, 'handleProviderCallback'])
        ->where('provider', '[a-zA-Z0-9_-]+')
        ->name('callback');
});


require __DIR__.'/auth.php'; // Fortify routes

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});

// Staff Portal Routes
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/dashboard', StaffDashboard::class)->name('dashboard');

    // Core Management
    Volt::route('/bikes', 'staff.bike.bike-manager')->name('bikes.index');
    Volt::route('/rentals', 'staff.rental.rental-manager')->name('rentals.index');
    Volt::route('/pax-profiles', 'staff.pax-profile.pax-profile-manager')->name('pax-profiles.index');
    Volt::route('/points-of-interest', 'staff.point-of-interest.point-of-interest-manager')->name('points-of-interest.index');
    Volt::route('/ship-departures', 'staff.ship-departure.ship-departure-manager')->name('ship-departures.index');

    // User & Depot Administration
    Volt::route('/team/depot-supervisor-manager', 'staff.team.depot-supervisor-manager')->name('team.depot-supervisor-manager');
    Volt::route('/users/spatie-role-manager', 'staff.user.user-spatie-role-manager')->name('user.spatie-role-manager');

});
