<?php

namespace App\Http\Controllers;

use App\Http\Requests\Need\StoreNeedRequest;
use App\Models\Need;
use App\Models\NeedSupport;
use App\Models\Profile;
use App\Support\NeedPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Everything the three public tabs read: the list (`/explore`), the recap
 * (`/reports`), and the submission that adds a row (`POST /needs`). All of it
 * comes from the `needs` table in D1 — the compiled `needs` config only holds
 * the categories now.
 */
class NeedController extends Controller
{
    /**
     * Browse every need around Sukajadi: the same rows the map plots, rendered
     * as text with a free-text search and the category chips.
     */
    public function index(): View
    {
        return view('explore.index', [
            'categories' => config('needs.categories'),
            'needs' => NeedPresenter::collection($this->query()->get()),
        ]);
    }

    /**
     * Recap of what has been reported so far: totals, a per-category breakdown,
     * and the needs ranked by support.
     */
    public function report(): View
    {
        $needs = NeedPresenter::collection($this->query()->get());
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

            if ($need['opportunity']) {
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

        return view('explore.report', [
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

        $profile = $this->resolveProfile($request);
        $title = trim((string) $validated['title']);
        $location = trim((string) ($validated['location'] ?? ''));

        Need::create([
            'profile_id' => $profile->id,
            'title' => $title,
            'marker_label' => $title,
            'category' => $validated['category'],
            'location' => $location !== '' ? $location : null,
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'area' => trim((string) ($validated['area'] ?? '')) ?: null,
            // A submission carries no coordinates yet, so it is listed and
            // ranked but not plotted (the map skips rows without a point).
            'status' => 'aktif',
        ]);

        return redirect()
            ->route('profile')
            ->with('status', 'Kebutuhan berhasil diajukan.');
    }

    /**
     * The map, the list and the recap read the same rows in the same order.
     */
    private function query(): Builder
    {
        return Need::query()->latest('created_at')->latest('id');
    }

    /**
     * Same lazy provisioning as the profile page: the deployment credential is
     * not necessarily a `profiles` row, so the first activity creates one.
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
