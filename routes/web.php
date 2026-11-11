<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NeedController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/explore', [NeedController::class, 'index'])->name('needs.index');

Route::get('/reports', [NeedController::class, 'report'])->name('needs.report');

// The Profil tab: `/profile` is the signed-in page, and a guest never sees it.
// The auth middleware sends the guest to `/auth` (with `?redirect=/profile`),
// so the tab lands on the sign-in screen until there is a session.
Route::get('/profile', ProfileController::class)
    ->middleware('auth.redirect')
    ->name('profile');

Route::middleware('guest:seal')->group(function (): void {
    Route::get('/auth', [AuthController::class, 'create'])->name('auth');
    Route::post('/auth', [LoginController::class, 'store'])->name('auth.login');
    Route::post('/auth/register', [RegisterController::class, 'store'])->name('auth.register');
});

// Public on purpose: an already signed-in visitor may still activate a second
// account, and the token alone decides which row it activates.
Route::get('/verify', EmailVerificationController::class)
    ->name('register.verify');

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth:seal')
    ->name('logout');

// Signed-in pages: a guest is redirected to /auth?redirect=<page> and returns
// there after signing in, instead of being dropped on the bare form.
Route::middleware('auth.redirect')->group(function (): void {
    Route::post('/needs', [NeedController::class, 'store'])->name('needs.store');
});
