<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Gaya peta raster Leaflet
    |--------------------------------------------------------------------------
    |
    | URL template untuk tile raster Leaflet. Default memakai tile
    | OpenStreetMap yang gratis dan tidak memerlukan API key. Mode gelap
    | diterapkan dengan filter CSS pada `.leaflet-tile-pane`, bukan style
    | terpisah, sehingga ketiga URL memakai template yang sama.
    | Ganti nilainya bila ingin memakai penyedia tile raster lain.
    |
    */

    'map' => [
        'style_url' => env('NEARFIND_MAP_STYLE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'dark_style_url' => env('NEARFIND_MAP_DARK_STYLE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        // Template yang sama untuk terang dan gelap; lihat filter CSS pada
        // `.leaflet-tile-pane` untuk tampilan mode gelap.
        'light_style_url' => env('NEARFIND_MAP_LIGHT_STYLE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tempat menarik (POI) dari OpenStreetMap
    |--------------------------------------------------------------------------
    |
    | Data POI dibaca langsung dari Overpass dan Nominatim saat peta dimuat,
    | lalu dikirim ke klien lewat data island. Bersifat read-only: tidak ada
    | baris yang ditulis ke D1. Keduanya gratis tanpa API key. Bila salah satu
    | gagal, peta tetap tampil seperti biasa.
    |
    */

    'poi' => [
        'enabled' => env('NEARFIND_POI_ENABLED', true),
        'overpass_endpoint' => env('NEARFIND_OVERPASS_ENDPOINT', 'https://overpass.private.coffee/api/interpreter'),
        'nominatim_reverse_endpoint' => env('NEARFIND_NOMINATIM_REVERSE_ENDPOINT', 'https://nominatim.openstreetmap.org/reverse'),
        'search_radius_m' => (int) env('NEARFIND_POI_SEARCH_RADIUS_M', 1000),
        'max_results' => (int) env('NEARFIND_POI_MAX_RESULTS', 20),
        'request_timeout_ms' => (int) env('NEARFIND_POI_REQUEST_TIMEOUT_MS', 8000),
        'cache_ms' => (int) env('NEARFIND_POI_CACHE_MS', 300000),
        'tags' => [
            'amenity' => [
                'place_of_worship',
                'school',
                'clinic',
                'hospital',
                'pharmacy',
                'cafe',
                'restaurant',
                'bank',
                'atm',
                'post_office',
                'library',
                'police',
            ],
            'shop' => ['supermarket', 'convenience', 'bakery'],
            'leisure' => ['park'],
            'tourism' => ['museum'],
        ],
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
            'color' => '#6b6256',
            'soft_color' => 'rgba(107, 98, 86, 0.22)',
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
            'color' => '#7a7166',
            'soft_color' => 'rgba(122, 113, 102, 0.22)',
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
            'color' => '#a39684',
            'soft_color' => 'rgba(163, 150, 132, 0.22)',
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
