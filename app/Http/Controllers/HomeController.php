<?php

namespace App\Http\Controllers;

use App\Models\Need;
use App\Support\NeedPresenter;
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

        return view('home', [
            'categories' => config('needs.categories'),
            'needs' => NeedPresenter::collection(Need::query()->latest('created_at')->latest('id')->get()),
            'mapStyleUrl' => config('needs.map.style_url'),
            'authenticated' => $user !== null,
            'sealUser' => $user,
        ]);
    }
}
