<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Demo koneksi DB (read-only) — buka http://localhost:8000/dbcheck
Route::get('/dbcheck', function () {
    return response()->json([
        'driver' => DB::connection('d1')->getDriverName(),
        'tables' => DB::connection('d1')->select('SELECT name FROM sqlite_master ORDER BY name'),
        'config_rows' => DB::connection('d1')->table('config')->count(),
    ]);
});
