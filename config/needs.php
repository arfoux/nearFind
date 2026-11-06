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
    | Kebutuhan dummy
    |--------------------------------------------------------------------------
    |
    | Koordinat berada di kawasan Sukajadi dan sekitarnya, Bandung. Semua
    | nilai di sini bersifat sementara dan mudah diganti dengan hasil query
    | dari database ketika backend sudah tersedia.
    |
    */

    'needs' => [
        [
            'id' => 'laundry-kiloan',
            'title' => 'Jasa Laundry Kiloan',
            'marker_label' => 'Laundry Kiloan',
            'category' => 'household',
            'location' => 'Jl. Sukajadi No. 18, Sukajadi',
            'area' => 'Sukajadi, Bandung',
            'distance_km' => 0.8,
            'supporters' => 24,
            'hours_ago' => 52,
            'description' => 'Warga perumahan kesulitan mencari laundry kiloan terdekat. Mereka harus menempuh lebih dari 2 km ke jalan utama. Layanan laundry kiloan di sekitar RW akan sangat membantu aktivitas harian warga.',
            'latitude' => -6.8905,
            'longitude' => 107.5944,
            'opportunity' => true,
        ],
        [
            'id' => 'servis-ac',
            'title' => 'Jasa Servis AC',
            'marker_label' => 'Servis AC',
            'category' => 'repair',
            'location' => 'Jl. Setiabudi No. 112, Gegerkalong',
            'area' => 'Gegerkalong, Bandung',
            'distance_km' => 1.4,
            'supporters' => 19,
            'hours_ago' => 5,
            'description' => 'Banyak warga membutuhkan teknisi AC langganan yang cepat datang, terutama saat musim panas. Saat ini warga harus menghubungi teknisi dari luar kelurahan.',
            'latitude' => -6.8875,
            'longitude' => 107.5978,
            'opportunity' => true,
        ],
        [
            'id' => 'bibit-tanaman',
            'title' => 'Bibit Tanaman',
            'marker_label' => 'Bibit Tanaman',
            'category' => 'retail',
            'location' => 'Jl. Surya Sumantri No. 45, Sukawarna',
            'area' => 'Sukawarna, Bandung',
            'distance_km' => 2.1,
            'supporters' => 15,
            'hours_ago' => 26,
            'description' => 'Warga ingin menanam sayur di pekarangan, tetapi belum ada penjual bibit tanaman terdekat. Toko bibit keliling atau kios kecil akan membantu kebutuhan ini.',
            'latitude' => -6.8943,
            'longitude' => 107.5979,
            'opportunity' => true,
        ],
        [
            'id' => 'jasa-fotokopi',
            'title' => 'Jasa Fotokopi',
            'marker_label' => 'Fotokopi',
            'category' => 'retail',
            'location' => 'Jl. Pasteur No. 8, Pasteur',
            'area' => 'Pasteur, Bandung',
            'distance_km' => 1.7,
            'supporters' => 12,
            'hours_ago' => 9,
            'description' => 'Pelajar dan pekerja sekitar kesulitan mencetak serta memfotokopi dokumen. Layanan fotokopi dekat permukiman akan menghemat waktu warga.',
            'latitude' => -6.8912,
            'longitude' => 107.5908,
            'opportunity' => true,
        ],
        [
            'id' => 'warung-sayur',
            'title' => 'Warung Sayur Segar',
            'marker_label' => 'Warung Sayur',
            'category' => 'culinary',
            'location' => 'Jl. Sukajadi No. 76, Sukajadi',
            'area' => 'Sukajadi, Bandung',
            'distance_km' => 1.1,
            'supporters' => 31,
            'hours_ago' => 74,
            'description' => 'Warga lansia membutuhkan warung sayur yang dekat dan menyediakan kebutuhan masak harian. Saat ini mereka harus berjalan jauh atau menunggu pedagang keliling.',
            'latitude' => -6.8936,
            'longitude' => 107.5924,
            'opportunity' => true,
        ],
        [
            'id' => 'klinik-24-jam',
            'title' => 'Klinik 24 Jam',
            'marker_label' => 'Klinik 24 Jam',
            'category' => 'health',
            'location' => 'Jl. Setiabudi No. 210, Gegerkalong',
            'area' => 'Gegerkalong, Bandung',
            'distance_km' => 1.9,
            'supporters' => 42,
            'hours_ago' => 8,
            'description' => 'Belum ada klinik yang buka 24 jam di sekitar permukiman. Warga membutuhkan akses layanan kesehatan cepat, terutama pada malam hari dan akhir pekan.',
            'latitude' => -6.8868,
            'longitude' => 107.5910,
            'opportunity' => true,
        ],
    ],
];
