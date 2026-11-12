<?php

namespace App\Http\Controllers;

use App\Http\Requests\Need\StoreNeedRequest;
use App\Models\Need;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
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
            'needs' => $this->present(Need::all()),
        ]);
    }

    /**
     * Recap of what has been reported so far: totals, a per-category breakdown,
     * and the needs ranked by support.
     */
    public function report(): View
    {
        $needs = $this->present(Need::all());
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
        $title = trim((string) $validated['title']);
        $location = trim((string) ($validated['location'] ?? ''));

        $need = Need::create([
            'profile_id' => $profile->id,
            'title' => $title,
            'marker_label' => $title,
            'category' => $validated['category'],
            'location' => $location !== '' ? $location : null,
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'area' => trim((string) ($validated['area'] ?? '')) ?: null,
            // The pin the form required: without it the need never reaches the map.
            'latitude' => (float) $validated['latitude'],
            'longitude' => (float) $validated['longitude'],
            'status' => 'aktif',
        ]);

        // Back to the map, not the profile: the visitor just placed a pin and
        // wants to see it land there, with the card open on the new need.
        return redirect()
            ->route('home', ['need' => $need->id])
            ->with('status', 'Kebutuhan “'.$title.'” berhasil diajukan dan sudah tampil di peta.');
    }

    /**
     * Withdraw one of your own submissions. Ownership is checked against the
     * signed-in profile, so a guessed id from someone else is a 404 rather than
     * a silent delete.
     */
    public function destroy(string $id): RedirectResponse
    {
        $user = request()->user();
        $profile = Profile::where('email', $user->email)->first();

        if ($profile === null) {
            abort(404);
        }

        $need = Need::where('id', $id)->first();

        if ($need === null || (int) $need->profile_id !== (int) $profile->id) {
            abort(404);
        }

        $need->delete();

        return redirect()
            ->route('profile')
            ->with('status', 'Pengajuan dihapus.');
    }

    /**
     * Row → plain array, inline: the compiled rung has no surface for an app
     * service class, so the controller produces the shape its views read. The
     * field names are the ones resources/js/app.js consumes for the map.
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
                'marker_label' => (string) ($need->marker_label ?: $need->title),
                'category' => (string) $need->category,
                'location' => (string) ($need->location ?? ''),
                'area' => (string) ($need->area ?? ''),
                'distance_km' => (float) ($need->distance_km ?? 0),
                'supporters' => (int) $need->supporters,
                'created_at' => (string) ($need->created_at ?? ''),
                'description' => (string) ($need->description ?? ''),
                'latitude' => $need->latitude !== null ? (float) $need->latitude : null,
                'longitude' => $need->longitude !== null ? (float) $need->longitude : null,
                'opportunity' => (bool) $need->opportunity,
            ];
        }

        return $rows;
    }

    /**
     * The map, the list and the recap read the same rows in the same order.
     */
    /**
     * Same lazy provisioning as the profile page: the deployment credential is
     * not necessarily a `profiles` row, so the first activity creates one.
     */
}
