<?php

namespace App\Http\Controllers;

use App\Models\Need;
use App\Models\NeedSupport;
use App\Models\Profile;
use App\Support\NeedPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The Profil tab for a signed-in warga: who they are, what they submitted, the
 * needs they backed, and the reports they filed. Everything comes from D1 — no
 * value here is a compiled snapshot.
 */
class ProfileController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $profile = $this->resolveProfile($request);

        $submittedNeeds = Need::where('profile_id', $profile->id)->latest()->limit(30)->get();

        $supportedNeeds = Need::whereIn(
            'id',
            // The pivot as a plain query: the compiled rung has no relation objects.
            NeedSupport::where('profile_id', $profile->id)
                ->latest('created_at')
                ->limit(30)
                ->get()
                ->map(static fn ($support): mixed => $support->need_id)
                ->all(),
        )->get();

        return view('profile.index', [
            'profile' => $profile,
            'email' => $user->email,
            'submittedNeeds' => NeedPresenter::collection($submittedNeeds),
            'supportedNeeds' => NeedPresenter::collection($supportedNeeds),
            'categories' => config('needs.categories'),
            'stats' => [
                'submitted' => $submittedNeeds->count(),
                'supported' => $supportedNeeds->count(),
            ],
        ]);
    }

    /**
     * The deployment credential is not necessarily a `profiles` row, so the
     * first visit creates the row it belongs to.
     */
    private function resolveProfile(Request $request): Profile
    {
        $user = $request->user();

        $profile = Profile::where('email', $user->email)->first();

        if ($profile !== null) {
            return $profile;
        }

        return Profile::create([
            'email' => $user->email,
            'name' => $user->name,
            'username' => $this->uniqueUsername($user->email),
        ]);
    }

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
            $base = 'warga';
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
