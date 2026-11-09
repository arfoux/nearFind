<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * The link in the verification mail. Public on purpose: a visitor who is
     * already signed in still has to be able to activate a second account, and
     * the token alone decides what it activates.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $token = (string) $request->query('token', '');
        $profile = Profile::where('email_verification_token', $token)->first();

        if ($profile === null) {
            return redirect()
                ->route('auth')
                ->with('status', 'Tautan verifikasi tidak dikenali atau sudah dipakai.');
        }

        $profile->email_verified_at = now();
        $profile->email_verification_token = null;
        $profile->save();

        return redirect()
            ->route('auth')
            ->with('status', 'Email '.$profile->email.' terverifikasi. Silakan masuk.');
    }
}
