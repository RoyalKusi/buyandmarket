<?php

use App\Http\Middleware\ScopeQueriesToActingSeller;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // TDD §7.1: the Livewire web client authenticates via session cookie
        // and shares the same /api/v1 authorization codepath as Bearer-token
        // clients (mobile, third-party integrations).
        $middleware->statefulApi();

        $middleware->alias([
            'seller.scope' => ScopeQueriesToActingSeller::class,
        ]);

        // TDD §8.5: "a seller's API token cannot retrieve another seller's
        // ... even with a guessed ID (404, not 403)." That only holds if the
        // seller-ownership scope is active before Laravel resolves a route's
        // {product}/{variant} model binding — otherwise the binding succeeds
        // unscoped and only the Policy check below it can deny (403).
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ScopeQueriesToActingSeller::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
