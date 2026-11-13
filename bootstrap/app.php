<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // El mostrador de laboratorio tiene su propio inicio de sesión, separado del panel interno.
        $middleware->redirectGuestsTo(fn (Request $request): string => $request->routeIs('laboratorio.*')
            ? route('laboratorio.login')
            : route('login'));
        $middleware->redirectUsersTo(fn (Request $request): string => $request->routeIs('laboratorio.*')
            ? route('laboratorio.index')
            : route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
