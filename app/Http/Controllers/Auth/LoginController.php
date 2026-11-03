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
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private readonly Sealer $sealer) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $configuredEmail = Str::lower(trim((string) config('seal.auth.email')));
        $configuredHash = trim((string) config('seal.auth.password_hash'));

        $valid = $configuredEmail !== ''
            && $configuredHash !== ''
            && hash_equals($configuredEmail, $request->email())
            && Hash::check((string) $request->input('password'), $configuredHash);

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

        return redirect()->intended(route('home'))->withCookie($cookie);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', 'Kamu sudah keluar dari akun warga.')
            ->withCookie(cookie()->forget((string) config('seal.session.cookie')));
    }
}
