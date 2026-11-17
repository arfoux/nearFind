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

## Menjalankan di lokal

Butuh PHP 8.3+, Composer, dan Node 22+. Perintahnya ada di
**[CONTRIBUTING.md § Setup lokal](CONTRIBUTING.md#setup-lokal)**.

## Kontribusi

`main` diproteksi: semua perubahan lewat pull request, dan PR di-merge **manual** (squash) oleh
manusia setelah CI hijau - tidak ada job yang meng-arm auto-merge. Aturan lengkapnya - branch,
commit, alur merge, dan larangannya - ada di **[CONTRIBUTING.md](CONTRIBUTING.md)**.

## Lisensi

MIT.
