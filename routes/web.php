<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NeedController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
| Public tabs. Each one is the page a bottom-nav tab opens, and each renders its
| own skeleton first (partials/skeleton.blade.php) so a tab never waits on a
| blank screen.
*/
Route::get('/', HomeController::class)->name('home');
Route::get('/explore', [NeedController::class, 'index'])->name('needs.index');
Route::get('/reports', [NeedController::class, 'report'])->name('needs.report');

// The Profil tab. A guest is sent to /auth (with the intended URL stashed, so
// signing in returns here); the page itself only ever renders a session.
Route::get('/profile', ProfileController::class)
    ->middleware('auth:seal')
    ->name('profile');

// Submitting a need writes a `needs` row and sends the visitor back to a page.
Route::post('/needs', [NeedController::class, 'store'])
    ->middleware('auth:seal')
    ->name('needs.store');

// Withdrawing one of your own submissions.
Route::delete('/needs/{id}', [NeedController::class, 'destroy'])
    ->middleware('auth:seal')
    ->name('needs.destroy');

Route::middleware('guest:seal')->group(function (): void {
    Route::get('/auth', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('auth.login');
    Route::post('/register', [RegisterController::class, 'store'])->name('auth.register');
});

// Public on purpose: an already signed-in visitor may still activate a second
// account, and the token alone decides which row it activates.
Route::get('/verify', EmailVerificationController::class)->name('register.verify');

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth:seal')
    ->name('logout');

/*
| Edge ports. These are the same actions again, on paths only the compiled
| worker answers (`ports/*.mjs`, listed in registry.json). The code they run —
| a custom middleware, an app service, `cookie()` — has no compiled surface, so
| the Worker's copy lives in a port and PHP keeps these paths for
| `php artisan serve` and the tests. Both sides answer one set of URLs.
*/
Route::post('/edge/login', [LoginController::class, 'store'])->name('edge.login');
Route::post('/edge/register', [RegisterController::class, 'store'])->name('edge.register');
Route::post('/edge/logout', [LoginController::class, 'destroy'])->middleware('auth:seal')->name('edge.logout');
Route::post('/edge/needs', [NeedController::class, 'store'])->middleware('auth:seal')->name('edge.needs.store');
