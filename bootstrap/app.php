<?php

use App\Http\Middleware\RedirectGuestsToAuth;
use App\Http\Middleware\RedirectTabRequests;
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
        $middleware->encryptCookies(except: ['nearfind_seal']);
        $middleware->redirectGuestsTo(fn () => route('auth'));
        $middleware->redirectUsersTo(fn () => route('home'));

        // `/?tab=jelajahi` is how the live shell asks for a tab after a swap.
        // Only a full document load is redirected: the shell fetches its own
        // pages and must not be bounced back to the map (see the middleware).
        $middleware->web(append: [RedirectTabRequests::class]);

        // Protected pages say `auth.redirect` instead of `auth:seal`, so a guest
        // keeps the page they asked for (`?redirect=` on /auth) and lands back
        // on it after signing in, rather than being dropped on the bare form.
        $middleware->alias([
            'auth.redirect' => RedirectGuestsToAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
