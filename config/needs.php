<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Gaya peta vector tile
    |--------------------------------------------------------------------------
    |
    | URL style MapLibre untuk peta. Default memakai OpenFreeMap yang gratis
    | dan tidak memerlukan API key. Ganti nilainya bila ingin memakai style
    | atau penyedia vector tile lain.
    |
    */

    'map' => [
        'style_url' => env('NEARFIND_MAP_STYLE_URL', 'https://tiles.openfreemap.org/styles/dark'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Kategori kebutuhan
    |--------------------------------------------------------------------------
    |
    | Data ini menjadi sumber awal untuk chip kategori, filter, marker, dan
    | kartu kebutuhan. Nantinya daftar ini dapat diganti dengan data dari
    | backend tanpa mengubah komponen UI.
    |
    */

    'categories' => [
        [
            'id' => 'all',
            'label' => 'Semua',
            'icon' => 'grid',
            'color' => '#A78BFA',
            'soft_color' => 'rgba(139, 92, 246, 0.22)',
        ],
        [
            'id' => 'household',
            'label' => 'Jasa Rumah Tangga',
            'icon' => 'home',
            'color' => '#FB923C',
            'soft_color' => 'rgba(251, 146, 60, 0.22)',
        ],
        [
            'id' => 'repair',
            'label' => 'Servis & Reparasi',
            'icon' => 'wrench',
            'color' => '#60A5FA',
            'soft_color' => 'rgba(96, 165, 250, 0.22)',
        ],
        [
            'id' => 'culinary',
            'label' => 'Kuliner',
            'icon' => 'utensils',
            'color' => '#FB7185',
            'soft_color' => 'rgba(251, 113, 133, 0.22)',
        ],
        [
            'id' => 'retail',
            'label' => 'Retail',
            'icon' => 'bag',
            'color' => '#F0ABFC',
            'soft_color' => 'rgba(240, 171, 252, 0.22)',
        ],
        [
            'id' => 'health',
            'label' => 'Kesehatan',
            'icon' => 'cross',
            'color' => '#F87171',
            'soft_color' => 'rgba(248, 113, 113, 0.22)',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Data kebutuhan
    |--------------------------------------------------------------------------
    |
    | Kebutuhan TIDAK lagi disimpan di sini. Barisnya ada di tabel `needs` (D1 di
    | produksi, SQLite di lokal) dan dibaca lewat App\Models\Need; 6 baris contoh
    | diisi oleh database/seeders/NeedSeeder.php. Yang tetap di config hanya
    | kategori, karena kategori adalah bahasa UI (chip, warna, ikon), bukan data.
    |
    */

];
