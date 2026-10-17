<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'staff' => \App\Http\Middleware\EnsureStaff::class,
            'client' => \App\Http\Middleware\EnsureClient::class,
        ]);
        $middleware->redirectUsersTo(fn () => route('account'));
        $middleware->trustProxies(at: '*'); // runs behind tunnels / load balancers
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('staff/*', 'admin/*') ? route('filament.admin.auth.login') : route('account.login'));
        $middleware->validateCsrfTokens(except: ['payments/webhook']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
