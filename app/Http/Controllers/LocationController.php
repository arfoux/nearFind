<?php

namespace App\Http\Controllers;

use App\Http\Requests\Location\StoreLocationRequest;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Places a warga saves from the map. Nominatim supplies the lookup, but the
 * row the visitor chooses is written here so it survives a reload.
 */
class LocationController extends Controller
{
    /** Reuse a row whose pin is this close to the new one (about 100 metres). */
    private const DEDUP_RADIUS_METERS = 100.0;

    public function store(StoreLocationRequest $request): RedirectResponse|JsonResponse
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

        $name = trim((string) $validated['name']);
        $normalized = $this->normalizeName($name);
        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];

        // A name alone is not a place: two warung with the same name on opposite
        // sides of the district are different rows. Only a nearby pin reuses one.
        foreach (Location::query()->whereRaw('lower(name) = ?', [$normalized])->get() as $existing) {
            if ($this->distanceMeters(
                (float) $existing->latitude,
                (float) $existing->longitude,
                $latitude,
                $longitude,
            ) <= self::DEDUP_RADIUS_METERS) {
                return $this->respond($request, $existing, true);
            }
        }

        $user = $request->user();
        $authorEmail = $user !== null
            ? (string) $user->email
            : (string) config('seal.auth.email');

        $location = Location::create([
            'name' => $name,
            'category' => (string) $validated['category'],
            'address' => trim((string) ($validated['address'] ?? '')) ?: null,
            'area' => trim((string) ($validated['area'] ?? '')) ?: null,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'source' => 'warga',
            'author_email' => $authorEmail,
        ]);

        return $this->respond($request, $location, false);
    }

    /**
     * Both the created and the reused row answer the same way: a redirect the
     * browser follows to the pin, or a JSON body for a fetch client.
     */
    private function respond(Request $request, Location $location, bool $reused): RedirectResponse|JsonResponse
    {
        $message = $reused
            ? 'Tempat “'.$location->name.'” sudah tersimpan di titik ini.'
            : 'Tempat “'.$location->name.'” berhasil disimpan dan sudah tampil di peta.';

        $row = $this->present($location);
        $target = route('home', ['location' => $row['id']]);

        if ($request->expectsJson()) {
            return response()->json([
                'location' => $row,
                'status' => $message,
                'redirect' => $target,
            ]);
        }

        return redirect()->to($target)->with('status', $message);
    }

    /**
     * @return array<string, string|float>
     */
    private function present(Location $location): array
    {
        return [
            'id' => (string) $location->id,
            'name' => (string) $location->name,
            'category' => (string) $location->category,
            'address' => (string) ($location->address ?? ''),
            'area' => (string) ($location->area ?? ''),
            'latitude' => (float) $location->latitude,
            'longitude' => (float) $location->longitude,
            'source' => (string) $location->source,
            'created_at' => (string) ($location->created_at ?? ''),
        ];
    }

    private function normalizeName(string $name): string
    {
        return Str::lower((string) preg_replace('/\s+/u', ' ', trim($name)));
    }

    private function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $radius = 6_371_000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $radius * $c;
    }
}
