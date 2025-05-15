<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Http\Controllers\SocialiteLoginController; // For Socialite

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Route 1 (Original simpler dashboard route)
Route::view('dashboard', 'dashboard') // Path is /dashboard
    ->middleware(['auth', 'verified'])
    ->name('dashboard.simple'); // Renamed to avoid conflict

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile'); // Redirect /settings to /settings/profile

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

// --- Staff Socialite SSO Routes ---
// These routes handle the redirection to the OAuth provider and the callback.
Route::prefix('auth')->name('auth.social.')->group(function () {
    Route::get('/{provider}/redirect', [SocialiteLoginController::class, 'redirectToProvider'])
        ->where('provider', '[a-zA-Z0-9_-]+')
        ->name('redirect');

    Route::get('/{provider}/callback', [SocialiteLoginController::class, 'handleProviderCallback'])
        ->where('provider', '[a-zA-Z0-9_-]+')
        ->name('callback');
});
// Example of a login link you might put in your staff login view (e.g., in resources/views/auth/login.blade.php):
// <a href="{{ route('auth.social.redirect', 'google') }}">Login with Google</a>

// Fortify/Jetstream authentication routes (login, register, password reset etc.)
require __DIR__.'/auth.php';

// Route 2 (Jetstream's default dashboard route group)
Route::middleware([
    'auth:sanctum', // Ensures user is authenticated for web routes via Sanctum if SPA, or session
    config('jetstream.auth_session'), // Standard Jetstream session middleware
    'verified', // Ensures email is verified if that feature is enabled
])->group(function () {
    Route::get('/dashboard', function () { // Path is also /dashboard
        return view('dashboard');
    })->name('dashboard'); // This is typically Jetstream's primary dashboard route name
});

// If you have other Volt routes or application-specific routes, they can go here.
