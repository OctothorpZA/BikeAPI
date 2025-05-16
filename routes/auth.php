<?php

use App\Http\Controllers\Auth\VerifyEmailController; // Keep if Fortify uses it, or remove if Fortify has its own internal handler
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('guest')->group(function () {
    Volt::route('login', 'auth.login')
        ->name('login');

    Volt::route('register', 'auth.register')
        ->name('register');

    Volt::route('forgot-password', 'auth.forgot-password')
        ->name('password.request');

    Volt::route('reset-password/{token}', 'auth.reset-password')
        ->name('password.reset');
});

Route::middleware('auth')->group(function () {
    // Fortify typically handles the route named 'verification.notice' for displaying the notice.
    // Comment out your Volt route if it conflicts.
    /*
    Volt::route('verify-email', 'auth.verify-email')
        ->name('verification.notice');
    */

    // Fortify also typically handles the route named 'verification.verify' for the actual verification.
    // Comment out your manual definition if it conflicts.
    /*
    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    */

    // Fortify typically handles the route named 'password.confirm'.
    // Comment out your Volt route if it conflicts.
    /*
    Volt::route('confirm-password', 'auth.confirm-password')
        ->name('password.confirm');
    */
});

// Ensure the namespace for Logout is correct as per your Livewire Actions setup
// If Logout is a Livewire Volt component, it might be defined elsewhere or like this:
// Volt::route('logout', 'auth.logout')->name('logout');
// If it's a standard controller action:
// use App\Http\Controllers\Auth\AuthenticatedSessionController; // Example
// Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
// Your uploaded auth.php had: Route::post('logout', App\Livewire\Actions\Logout::class)->name('logout');
// Ensure App\Livewire\Actions\Logout::class exists and is invokable or has a __invoke method.
Route::post('logout', \App\Livewire\Actions\Logout::class)
    ->middleware('auth') // Ensure logout is protected
    ->name('logout');
