<?php

namespace App\Http\Controllers;

use App\Http\Requests\Need\StoreNeedRequest;
use App\Models\Need;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NeedsController extends Controller
{
    /**
     * Browse every need around Sukajadi: the same list the map plots, rendered
     * as text with a free-text search and the category chips.
     */
    public function index(): View
    {
        return view('needs.index', [
            'categories' => config('needs.categories'),
            'needs' => config('needs.needs'),
        ]);
    }

    /**
     * Recap of what has been reported so far: totals, a per-category breakdown,
     * and the needs ranked by support.
     */
    public function report(): View
    {
        $needs = config('needs.needs');
        $categories = config('needs.categories');

        $perCategory = [];
        $supporters = 0;
        $opportunities = 0;
        $peak = 1;

        foreach ($needs as $need) {
            $category = (string) $need['category'];
            $perCategory[$category] = ($perCategory[$category] ?? 0) + 1;

            if ($perCategory[$category] > $peak) {
                $peak = $perCategory[$category];
            }

            $supporters += (int) $need['supporters'];

            if (! empty($need['opportunity'])) {
                $opportunities = $opportunities + 1;
            }
        }

        // Most supported first. Plain insertion sort: `usort` and the spaceship
        // operator are not part of the compiled surface.
        $ranked = [];

        foreach ($needs as $need) {
            $placed = false;
            $next = [];

            foreach ($ranked as $existing) {
                if (! $placed && (int) $need['supporters'] > (int) $existing['supporters']) {
                    $next[] = $need;
                    $placed = true;
                }

                $next[] = $existing;
            }

            if (! $placed) {
                $next[] = $need;
            }

            $ranked = $next;
        }

        return view('needs.report', [
            'categories' => $categories,
            'needs' => $ranked,
            'perCategory' => $perCategory,
            'peak' => $peak,
            'supporters' => $supporters,
            'opportunities' => $opportunities,
        ]);
    }

    /**
     * Store a need submitted from the map. The category is checked against the
     * compiled `needs` config (the FormRequest only proves it is a string).
     */
    public function store(StoreNeedRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $categoryIds = [];

        foreach (config('needs.categories') as $category) {
            if ((string) $category['id'] !== 'all') {
                $categoryIds[] = (string) $category['id'];
            }
        }

        if (! in_array($validated['category'], $categoryIds, true)) {
            throw ValidationException::withMessages([
                'category' => 'Pilih kategori yang tersedia.',
            ]);
        }

        $profile = Profile::where('email', $request->user()->email)->first() ?? $this->resolveProfile($request);
        $location = trim((string) ($validated['location'] ?? ''));

        Need::create([
            'profile_id' => $profile->id,
            'title' => trim($validated['title']),
            'category' => $validated['category'],
            'location' => $location !== '' ? $location : null,
            'status' => 'aktif',
        ]);

        return redirect()
            ->route('profile.index')
            ->with('status', 'Kebutuhan berhasil diajukan.');
    }

    /**
     * Same lazy provisioning as the profile page: the deployment credential is
     * not a `profiles` row, so the first activity creates one. Kept here rather
     * than calling an app-model helper the compiled rung cannot run.
     */
    private function resolveProfile(Request $request): Profile
    {
        $user = $request->user();

        $profile = Profile::where('email', $user->email)->first();

        if ($profile) {
            return $profile;
        }

        return Profile::create([
            'email' => $user->email,
            'name' => $user->name,
            'username' => $this->uniqueUsername($user->email),
            'notification_preferences' => [
                'need_updates' => true,
                'support_activity' => true,
                'weekly_digest' => false,
            ],
        ]);
    }

    /**
     * A unique handle for a profile created lazily at the edge. Same shape as
     * `ProfileController::uniqueUsername()`, written with the compiled surface.
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
