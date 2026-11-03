<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home', [
        'categories' => config('needs.categories'),
        'needs' => config('needs.needs'),
        'mapStyleUrl' => config('needs.map.style_url'),
        // Belum ada autentikasi. Saat auth ditambahkan, ganti dengan auth()->check().
        'authenticated' => false,
    ]);
})->name('home');
