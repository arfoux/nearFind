<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * The single auth page: the login and register forms share one screen and
     * one tab switch, so the visitor can move between them without a reload.
     */
    public function create(Request $request): View
    {
        $initialMode = in_array($request->query('mode'), ['daftar', 'register'], true)
            ? 'register'
            : 'login';

        return view('auth.index', ['initialMode' => $initialMode]);
    }
}
