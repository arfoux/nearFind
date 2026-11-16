<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\Seal\Sealer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __construct(private readonly Sealer $sealer) {}

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $configuredEmail = Str::lower(trim((string) config('seal.auth.email')));
        $configuredHash = trim((string) config('seal.auth.password_hash'));

        // One account, sealed into the deployment: no profile row, no
        // registration, and no verification gate to consult.
        if ($configuredEmail === '' || $configuredHash === ''
            || ! hash_equals($configuredEmail, $request->email())
            || ! Hash::check((string) $request->input('password'), $configuredHash)) {
            $request->hitRateLimiter();

            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi tidak cocok.',
            ]);
        }

        $request->clearRateLimiter();
        $request->session()->regenerate();

        $ttl = $request->remember()
            ? config('seal.session.remember_ttl')
            : config('seal.session.ttl');

        $ttlMs = Sealer::durationToMilliseconds((string) $ttl);

        $token = $this->sealer->seal(Sealer::PURPOSE_SESSION, [
            'email' => $request->email(),
            'name' => (string) config('seal.auth.name'),
        ], $ttlMs);

        $cookie = cookie(
            name: (string) config('seal.session.cookie'),
            value: $token,
            minutes: (int) ceil($ttlMs / 60_000),
            path: '/',
            domain: config('session.domain'),
            secure: null,
            httpOnly: true,
            raw: true,
            sameSite: 'lax',
        );

        return redirect()->intended($this->redirectTarget($request))->withCookie($cookie);
    }

    /**
     * The same-origin page the guest was sent here from (`?redirect=/profile`,
     * put there by the auth middleware), so signing in lands back on the tab
     * that asked for an account. Anything else falls back to the map.
     */
    private function redirectTarget(Request $request): string
    {
        $target = (string) $request->input('redirect', '');

        if ($target === '' || ! str_starts_with($target, '/') || str_starts_with($target, '//')) {
            return route('home');
        }

        return $target;
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', 'Kamu sudah keluar dari akunmu.')
            ->withCookie(cookie()->forget((string) config('seal.session.cookie')));
    }
}
