<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guests never see a page that needs an account: they are sent to `/auth`
 * with the page they asked for, and land back on it after signing in.
 *
 * The guard would otherwise bounce them to the bare `/auth` page (losing the
 * destination), which is why the protected groups name this middleware.
 */
final class RedirectGuestsToAuth
{
    public function handle(Request $request, Closure $next, ?string $guard = null): Response
    {
        if (auth($guard ?? 'seal')->check()) {
            return $next($request);
        }

        return redirect()->guest(route('auth'));
    }
}
