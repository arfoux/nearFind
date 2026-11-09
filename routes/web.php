<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NeedsController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/jelajah', [NeedsController::class, 'index'])->name('needs.index');

Route::get('/laporan', [NeedsController::class, 'report'])->name('needs.report');

Route::post('/kebutuhan', [NeedsController::class, 'store'])
    ->middleware('auth:seal')
    ->name('needs.store');

Route::middleware('guest:seal')->group(function (): void {
    Route::get('/masuk', [LoginController::class, 'create'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store'])->name('login.store');
    Route::get('/daftar', [RegisterController::class, 'create'])->name('register');
    Route::post('/daftar', [RegisterController::class, 'store'])->name('register.store');
});

// Public on purpose: an already signed-in visitor may still activate a second
// account, and the token alone decides which row it activates.
Route::get('/daftar/verifikasi', EmailVerificationController::class)
    ->name('register.verify');

Route::post('/keluar', [LoginController::class, 'destroy'])
    ->middleware('auth:seal')
    ->name('logout');

Route::get('/dasbor', DashboardController::class)
    ->middleware('auth:seal')
    ->name('dashboard');

Route::middleware('auth:seal')->group(function (): void {
    Route::get('/profil', [ProfileController::class, 'show'])->name('profile.index');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profil/kata-sandi', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::put('/profil/notifikasi', [ProfileController::class, 'updateNotifications'])->name('profile.notifications');
});
