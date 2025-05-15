<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Add your global middleware here
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Configure exception handling
    })
    ->withProviders([ // <--- ADD OR MODIFY THIS SECTION
        App\Providers\AppServiceProvider::class, // Default AppServiceProvider
        App\Providers\AuthServiceProvider::class, // <<<< REGISTER YOUR AuthServiceProvider HERE
        App\Providers\VoltServiceProvider::class, // If you have VoltServiceProvider registered here
        // Add other custom providers if any
        // Jetstream and Fortify providers are often auto-discovered or registered by their packages
    ])
    ->create();
