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
        // Kategori ini mencerminkan tag OSM yang diizinkan di needs.poi.tags
        // dan memakai ikon yang sudah ada di sprite, tanpa aset baru.
        [
            'id' => 'worship',
            'label' => 'Tempat Ibadah',
            'icon' => 'pin',
            'color' => '#6b6256',
            'soft_color' => 'rgba(107, 98, 86, 0.22)',
        ],
        [
            'id' => 'education',
            'label' => 'Sekolah',
            'icon' => 'clipboard',
            'color' => '#7a7166',
            'soft_color' => 'rgba(122, 113, 102, 0.22)',
        ],
        [
            'id' => 'bank',
            'label' => 'Bank',
            'icon' => 'bag',
            'color' => '#a39684',
            'soft_color' => 'rgba(163, 150, 132, 0.22)',
        ],
        [
            'id' => 'atm',
            'label' => 'ATM',
            'icon' => 'bag',
            'color' => '#7a7166',
            'soft_color' => 'rgba(122, 113, 102, 0.22)',
        ],
        [
            'id' => 'postal',
            'label' => 'Kantor Pos',
            'icon' => 'mail',
            'color' => '#6b6256',
            'soft_color' => 'rgba(107, 98, 86, 0.22)',
        ],
        [
            'id' => 'library',
            'label' => 'Perpustakaan',
            'icon' => 'clipboard',
            'color' => '#a39684',
            'soft_color' => 'rgba(163, 150, 132, 0.22)',
        ],
        [
            'id' => 'police',
            'label' => 'Kepolisian',
            'icon' => 'shield-check',
            'color' => '#7a7166',
            'soft_color' => 'rgba(122, 113, 102, 0.22)',
        ],
        [
            'id' => 'park',
            'label' => 'Taman',
            'icon' => 'map',
            'color' => '#6b6256',
            'soft_color' => 'rgba(107, 98, 86, 0.22)',
        ],
        [
            'id' => 'museum',
            'label' => 'Museum',
            'icon' => 'info',
            'color' => '#a39684',
            'soft_color' => 'rgba(163, 150, 132, 0.22)',
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

    /*
    |--------------------------------------------------------------------------
    | SEO dan discoverability
    |--------------------------------------------------------------------------
    |
    | Nilai bawaan metadata untuk kedua layout. Daftar kata kunci memakai
    | frase alami yang memang sesuai dengan peta, fasilitas umum, dan laporan
    | kebutuhan warga nearFind. Tidak ada layanan SEO eksternal atau API key.
    |
    */

    'seo' => [
        // Host produksi tetap agar canonical dan URL data terstruktur tetap absolut setelah prerender statis.
        'site_url' => 'https://nf.nietzsc.de',
        'site_name' => 'nearFind',
        'site_description' => 'Temukan kebutuhan warga dan fasilitas umum terdekat di sekitarmu lewat peta OpenStreetMap.',
        'keywords' => 'peta kebutuhan warga, peta fasilitas umum, peta di sekitar saya, peta lokasi terdekat, cari lokasi dekat sini, fasilitas umum terdekat, tempat terdekat, di sekitar saya, dekat sini, sekitar Sukajadi, Sukajadi Bandung, Gegerkalong, Sukawarna, Pasteur, kebutuhan warga, laporan kebutuhan warga, dukung kebutuhan warga, rekap kebutuhan warga, peluang usaha warga, tempat ibadah terdekat, rumah sakit terdekat, klinik dekat sini, fasilitas kesehatan terdekat, apotek di sekitar saya, sekolah terdekat, perpustakaan terdekat, kafe terdekat, restoran dekat sini, supermarket terdekat, minimarket di sekitar saya, toko roti terdekat, bank terdekat, ATM di sekitar saya, kantor pos terdekat, kantor polisi dekat sini, taman terdekat, museum di sekitar saya, jasa laundry terdekat, servis AC dekat sini, fotokopi terdekat, bibit tanaman, warung sayur, near me, nearby places, nearest places, places near me, points of interest, POI near me, OpenStreetMap, OSM map, map nearby, locations near me, community needs, facilities near me, interactive map, local services near me',
    ],
];
