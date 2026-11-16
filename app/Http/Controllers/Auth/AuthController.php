<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * One screen, one account: the sealed deployment credential. Registration
     * was removed, so the form always opens on the sign-in tab.
     */
    public function create(): View
    {
        return view('auth.index', ['initialMode' => 'login']);
    }
}
