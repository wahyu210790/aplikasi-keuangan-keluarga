<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Providers\AuthServiceProvider;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register custom middleware alias for household member isolation
        $middleware->alias([
            'household.member' => \App\Http\Middleware\EnsureHouseholdMember::class,
            'super_admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
        ]);        $middleware->priority([
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\EnsureHouseholdMember::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();

// Register custom AuthServiceProvider for policy mappings
$app->register(AuthServiceProvider::class);

return $app;
