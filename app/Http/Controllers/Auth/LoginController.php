<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Profile;
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
        $profile = Profile::findByEmail($request->email());

        // Two ways in, one of them new with registration:
        //  - the deployment credential (the `seal.auth` binding): its email is
        //    part of the credential, as before;
        //  - an account row created by /auth/register: its own email + hash, and only
        //    once the mailed link activated it.
        $valid = false;

        if ($configuredEmail !== '' && $configuredHash !== '' && hash_equals($configuredEmail, $request->email())
            && Hash::check((string) $request->input('password'), $configuredHash)) {
            $valid = true;
        } elseif ($profile !== null && $profile->password_hash
            && Hash::check((string) $request->input('password'), (string) $profile->password_hash)) {
            if (! $profile->hasVerifiedEmail()) {
                throw ValidationException::withMessages([
                    'email' => 'Email belum diverifikasi. Buka tautan verifikasi yang kami kirim ke '.$profile->email.'.',
                ]);
            }

            $valid = true;
        }

        if (! $valid) {
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
            'name' => $profile?->name ?: (string) config('seal.auth.name'),
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
            ->route('auth')
            ->with('status', 'Kamu sudah keluar dari akunmu.')
            ->withCookie(cookie()->forget((string) config('seal.session.cookie')));
    }
}
