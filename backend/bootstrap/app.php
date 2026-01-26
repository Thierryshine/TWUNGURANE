<?php

/**
 * TWUNGURANE - Bootstrap de l'application
 * 
 * Configuration et initialisation du framework Laravel
 */

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Configuration des middlewares API
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Alias pour les middlewares personnalisés
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'verified.phone' => \App\Http\Middleware\EnsurePhoneIsVerified::class,
        ]);

        // Rate limiting pour l'API
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Gestion personnalisée des exceptions API
    })->create();
