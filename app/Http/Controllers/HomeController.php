<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * The map page is rendered per request on purpose: the header and the
     * client-side gate read the signed-in state, and a build-time snapshot
     * would bake the guest state into every visit.
     */
    public function __invoke(): View
    {
        $user = auth('seal')->user();

        return view('home', [
            'categories' => config('needs.categories'),
            'needs' => config('needs.needs'),
            'mapStyleUrl' => config('needs.map.style_url'),
            'authenticated' => $user !== null,
            'sealUser' => $user,
        ]);
    }
}
