<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Need;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * The map page is rendered per request on purpose: the header and the
     * client-side gate read the signed-in state, and a build-time snapshot
     * would bake the guest state into every visit. The rows come from D1.
     */
    public function __invoke(Request $request): View
    {
        $user = auth('seal')->user();

        $rows = [];

        foreach (Need::all() as $need) {

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

        $locations = [];

        foreach (Location::all() as $location) {
            $locations[] = [
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

        return view('home', [
            'categories' => config('needs.categories'),
            'needs' => $rows,
            'locations' => $locations,
            'mapStyleUrl' => config('needs.map.style_url'),
            'mapDarkStyleUrl' => config('needs.map.dark_style_url'),
            'mapLightStyleUrl' => config('needs.map.light_style_url'),
            'poi' => config('needs.poi'),
            'authenticated' => $user !== null,
            'sealUser' => $user,
        ]);
    }
}
