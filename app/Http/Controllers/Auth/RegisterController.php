<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Mail\VerifyEmail;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    public function store(RegisterRequest $request): RedirectResponse
    {
        $email = Str::lower(trim((string) $request->input('email')));
        $name = trim((string) $request->input('name'));
        $password = (string) $request->input('password');

        // No "address belongs to the bootstrap credential" refusal here on
        // purpose: the deployment credential lives in the `seal` config group,
        // which the build deliberately keeps out of dist (it carries the keys
        // and the password hash), so a compiled `config('seal...')` read fails
        // the build and an `env()` read would be the only source. The invariant
        // is enforced where the credential is used instead — login tries the
        // binding first for its own address (LoginController and ports/login.mjs),
        // so a row that squats that address can never authenticate with it.
        $existing = Profile::where('email', $email)->first();

        if ($existing !== null) {
            // The address is taken. When it is the same person (the password
            // matches) and the account is still unverified, the mailed link is
            // re-issued: a lost or mistyped mail must not lock the address out of
            // its own registration. Anything else answers generically, so this
            // path can neither probe addresses nor mail-bomb them.
            // Inline column read on purpose: the compiled rung does not compile
            // an app-model method (`$existing->hasVerifiedEmail()` raises LW003
            // and the whole POST route is dropped), so the check asks the
            // attribute the cast already produced.
            if ($existing->email_verified_at !== null || ! Hash::check($password, (string) $existing->password_hash)) {
                throw ValidationException::withMessages([
                    'email' => 'Email ini sudah terdaftar. Coba masuk atau pakai email lain.',
                ]);
            }

            $this->sendVerification($existing);

            return redirect()
                ->route('login')
                ->with('status', 'Tautan verifikasi baru sudah dikirim ke '.$existing->email.'.');
        }

        // `Profile::create([...])` rather than `new Profile` + writes: the
        // compiled rung has the query builder, not the Eloquent instance, so a
        // bare `new Profile()` raises LW002 and takes the whole POST route with
        // it (the GET keeps answering, which is how it stays quiet).
        $profile = Profile::create([
            'email' => $email,
            'name' => $name,
            'username' => $this->uniqueUsername($email),
            'password_hash' => Hash::make($password),
            'notification_preferences' => [
                'need_updates' => true,
                'support_activity' => true,
                'weekly_digest' => false,
            ],
        ]);

        $this->sendVerification($profile);

        return redirect()
            ->route('login')
            ->with('status', 'Akun dibuat. Buka tautan verifikasi di email '.$profile->email.' untuk mengaktifkannya.');
    }

    /**
     * Issue a fresh token and mail the link through the `mail` config group
     * (Resend at the edge).
     *
     * Deliberately no `try`/`catch`: the compiled rung refuses the construct
     * (`unsupported construct: try`), and a failed send already answers 500 to
     * the visitor — itself the loud signal that the deployment is misconfigured.
     */
    private function sendVerification(Profile $profile): void
    {
        $profile->email_verification_token = Str::random(48);
        $profile->save();

        Mail::to((string) $profile->email)->send(new VerifyEmail(
            (string) $profile->name,
            url('/verify?token='.$profile->email_verification_token),
        ));
    }

    /**
     * A unique handle: the local part filtered to `[a-z0-9._-]`, then a numeric
     * suffix while the base is taken. Written with the compiled surface (no
     * fluent `Str::of()`), and it never trips the unique index on
     * `profiles.username` — two people called john@ may register in a row.
     */
    private function uniqueUsername(string $email): string
    {
        $raw = strtolower(Str::before($email, '@'));
        $base = '';

        for ($i = 0; $i < strlen($raw); $i++) {
            $ch = substr($raw, $i, 1);

            if (($ch >= 'a' && $ch <= 'z') || ($ch >= '0' && $ch <= '9') || $ch === '.' || $ch === '_' || $ch === '-') {
                $base .= $ch;
            }
        }

        $base = trim($base, '._-');

        if ($base === '') {
            $base = 'pengguna';
        }

        $base = substr($base, 0, 24);

        $username = $base;
        $suffix = 1;

        while (Profile::where('username', $username)->first() !== null) {
            $suffix++;
            $username = $base.$suffix;
        }

        return $username;
    }
}
