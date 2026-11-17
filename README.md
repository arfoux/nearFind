# nearFind

[![ci](https://github.com/arfoux/nearFind/actions/workflows/ci.yml/badge.svg)](https://github.com/arfoux/nearFind/actions/workflows/ci.yml) [![verdict: runs with declared gaps](https://img.shields.io/badge/verdict-RUNS%20WITH%20DECLARED%20GAPS-E0A126)](https://github.com/arfoux/laravel-workers/blob/main/docs/CI.md) [![license: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Aplikasi peta: daftar kebutuhan (baris D1/SQLite) per kategori - `config/needs.php` berisi pengaturan kategori, peta, dan POI - ditampilkan di atas peta
[Leaflet](https://leafletjs.com/). Laravel 13 (PHP 8.3+) di-compile
[laravel-workers](https://github.com/arfoux/laravel-workers) menjadi satu Cloudflare Worker; Vite +
Tailwind di klien. Tile raster berasal dari OpenStreetMap; URL template-nya bisa diganti lewat
`NEARFIND_MAP_STYLE_URL`, `NEARFIND_MAP_DARK_STYLE_URL`, atau `NEARFIND_MAP_LIGHT_STYLE_URL`
(default: `https://tile.openstreetmap.org/{z}/{x}/{y}.png`). Mode gelap memakai filter CSS.
Tempat menarik (POI) diambil dari Overpass dan Nominatim yang gratis serta tanpa API key;
endpoint-nya bisa diganti lewat `NEARFIND_OVERPASS_ENDPOINT` dan `NEARFIND_NOMINATIM_REVERSE_ENDPOINT`.
Data POI hanya dibaca dan tidak pernah disimpan di D1, dan kegagalan layanan ini tidak boleh
mengganggu peta.

## SEO

Metadata halaman (judul, deskripsi, kata kunci, canonical, robots, Open Graph,
Twitter Card, dan JSON-LD) disatukan di `resources/views/partials/seo/head.blade.php`
dan dipakai oleh kedua layout. Nilai per halaman berasal dari view rute; nilai
bawaan dan daftar kata kunci ada di `config/needs.php` pada `needs.seo`.
Halaman privat `/auth` dan `/profile` memakai `noindex` agar tidak masuk indeks.
Salinan halaman mengikuti fitur yang benar-benar ada; halaman publik memakai `index,follow`, halaman privat memakai `noindex`.

`public/sitemap.xml` adalah file statis untuk tiga halaman publik (`/`, `/explore`,
dan `/reports`) dan ikut terdeploy seperti `public/robots.txt`, yang berisi arahan
`Sitemap:`. Tidak ada rute baru, API key, atau layanan SEO eksternal yang dipakai.

## Menjalankan di lokal

Butuh PHP 8.3+, Composer, dan Node 22+. Perintahnya ada di
**[CONTRIBUTING.md § Setup lokal](CONTRIBUTING.md#setup-lokal)**.

## Kontribusi

`main` diproteksi: semua perubahan lewat pull request, dan PR di-merge **manual** (squash) oleh
manusia setelah CI hijau - tidak ada job yang meng-arm auto-merge. Aturan lengkapnya - branch,
commit, alur merge, dan larangannya - ada di **[CONTRIBUTING.md](CONTRIBUTING.md)**.

## Lisensi

MIT.
