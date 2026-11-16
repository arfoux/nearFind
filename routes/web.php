<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocationController;
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

// Saving a place found through Nominatim writes a `locations` row.
Route::post('/locations', [LocationController::class, 'store'])
    ->middleware('auth:seal')
    ->name('locations.store');
Route::middleware('guest:seal')->group(function (): void {
    Route::get('/auth', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('auth.login');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth:seal')
    ->name('logout');

/*
| Edge ports. These are the same actions again, on paths only the compiled
| worker answers (`ports/*.mjs`, listed in registry.json). The code they run,
| a custom middleware, an app service, `cookie()`, have no compiled surface, so
| the Worker's copy lives in a port and PHP keeps these paths for
| `php artisan serve` and the tests. Both sides answer one set of URLs.
*/
Route::post('/edge/login', [LoginController::class, 'store'])->name('edge.login');
Route::post('/edge/logout', [LoginController::class, 'destroy'])->middleware('auth:seal')->name('edge.logout');
Route::post('/edge/needs', [NeedController::class, 'store'])->middleware('auth:seal')->name('edge.needs.store');
Route::post('/edge/locations', [LocationController::class, 'store'])->middleware('auth:seal')->name('edge.locations.store');
