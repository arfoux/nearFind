<?php

namespace App\Http\Controllers;

use App\Models\Need;
use App\Models\NeedSupport;
use App\Models\Profile;
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
        $user = $request->user();

        $profile = Profile::where('email', $user->email)->first();

        if ($profile === null) {
            $local = strtolower(Str::before($user->email, '@'));
            $base = '';

            for ($index = 0; $index < strlen($local); $index++) {
                if (strlen($base) >= 24) {
                    break;
                }

                $char = substr($local, $index, 1);

                if (($char >= 'a' && $char <= 'z') || ($char >= '0' && $char <= '9') || $char === '.' || $char === '_' || $char === '-') {
                    $base .= $char;
                }
            }

            $base = trim($base, '._-');

            if ($base === '') {
                $base = 'warga';
            }

            $profile = Profile::create([
                'email' => $user->email,
                'name' => $user->name,
                'username' => $base,
            ]);
        }

        $submittedNeeds = Need::where('profile_id', $profile->id)->latest()->limit(30)->get();

        // Two plain queries, because the compiled rung cannot chain past `get()`.
        $supportedIds = [];

        foreach (NeedSupport::where('profile_id', $profile->id)->limit(30)->get() as $support) {
            $supportedIds[] = $support->need_id;
        }

        $supportedNeeds = $supportedIds === []
            ? []
            : Need::whereIn('id', $supportedIds)->limit(30)->get();

        return view('profile.index', [
            'profile' => $profile,
            'email' => $user->email,
            'submittedNeeds' => $this->present($submittedNeeds),
            'supportedNeeds' => $this->present($supportedNeeds),
            'categories' => config('needs.categories'),
            // `count()` on a query result is not part of the compiled surface,
            // so the tally is taken from the arrays the view already receives.
            'stats' => [
                'submitted' => count($this->present($submittedNeeds)),
                'supported' => count($this->present($supportedNeeds)),
            ],
        ]);
    }

    /**
     * Row → plain array, inline: the compiled rung has no surface for an app
     * service class, so the controller produces the shape its view reads.
     *
     * @param  iterable<int, Need>  $needs
     * @return array<int, array<string, mixed>>
     */
    private function present(iterable $needs): array
    {
        $rows = [];

        foreach ($needs as $need) {
            $rows[] = [
                'id' => (string) $need->id,
                'title' => (string) $need->title,
                'category' => (string) $need->category,
                'location' => (string) ($need->location ?? ''),
                'description' => (string) ($need->description ?? ''),
                'supporters' => (int) $need->supporters,
            ];
        }

        return $rows;
    }

    /**
     * The deployment credential is not necessarily a `profiles` row, so the
     * first visit creates the row it belongs to.
     */
}
