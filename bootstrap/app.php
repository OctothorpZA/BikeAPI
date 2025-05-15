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
        // This is where you would register global middleware.
        // Example: $middleware->append(MyGlobalMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // This is where you configure custom exception handling.
        // Example: $exceptions->dontReport(SpecificException::class);
    })
    ->withProviders([
        // Default Laravel service providers are often auto-discovered.
        // Explicitly registered providers:
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class, // Correctly registered for Policies
        App\Providers\VoltServiceProvider::class, // Correctly registered for Volt
        // App\Providers\FortifyServiceProvider::class, // Fortify is usually auto-discovered
        // App\Providers\JetstreamServiceProvider::class, // Jetstream is usually auto-discovered
        // Add any other custom application service providers here.
    ])
    // If you needed to customize facade aliases, it would be done with ->withFacades()
    // Example:
    // ->withFacades(true, [ // true to load default aliases, then add custom ones
    // 'CustomFacade' => App\Facades\CustomFacade::class,
    // ])
    // Since your Redis issue is resolved when using Sail, and no problematic 'Redis' alias
    // is visible here, this section is likely not needed for that.
    ->create();
