<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home', [
        'categories' => config('needs.categories'),
        'needs' => config('needs.needs'),
        'mapStyleUrl' => config('needs.map.style_url'),
        'authenticated' => auth()->check(),
    ]);
})->name('home');

Route::middleware('guest:seal')->group(function (): void {
    Route::get('/masuk', [LoginController::class, 'create'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/keluar', [LoginController::class, 'destroy'])
    ->middleware('auth:seal')
    ->name('logout');

Route::get('/dasbor', DashboardController::class)
    ->middleware('auth:seal')
    ->name('dashboard');
