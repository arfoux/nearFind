<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A tab is addressable from two places: its own endpoint (`/explore`, …) and a
 * tab name on the map (`/?tab=jelajahi`). Tabs are a CLIENT concern — the shell
 * keeps them in sync with the history API — so only a full document load of the
 * home page is rewritten here. A fetch from the SPA (`X-Requested-With`) or the
 * prerender/build pass is left alone, otherwise every in-place tab navigation
 * would bounce the map and the tab would never change.
 */
final class RedirectTabRequests
{
    /**
     * @var array<string, string>
     */
    private const TAB_ROUTES = [
        'peta' => 'home',
        'jelajahi' => 'needs.index',
        'laporan' => 'needs.report',
        'profil' => 'profile',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $tab = (string) $request->query('tab', '');

        if (
            $request->method() !== 'GET'
            || $request->path() !== '/'
            || $tab === ''
            || ! $request->headers->has('Sec-Fetch-Mode')
        ) {
            return $next($request);
        }

        $route = self::TAB_ROUTES[$tab] ?? null;

        if ($route === null || $route === 'home') {
            return $next($request);
        }

        return redirect()->route($route);
    }
}
