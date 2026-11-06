# nearFind

[![ci](https://github.com/arfoux/nearFind/actions/workflows/ci.yml/badge.svg)](https://github.com/arfoux/nearFind/actions/workflows/ci.yml) [![verdict: runs with declared gaps](https://img.shields.io/badge/verdict-RUNS%20WITH%20DECLARED%20GAPS-E0A126)](https://github.com/arfoux/laravel-workers/blob/main/docs/CI.md) [![license: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Aplikasi peta: daftar kebutuhan (`config/needs.php`) per kategori ditampilkan di atas peta
[MapLibre GL](https://maplibre.org/). Laravel 13 (PHP 8.3+) di-compile
[laravel-workers](https://github.com/arfoux/laravel-workers) menjadi satu Cloudflare Worker; Vite +
Tailwind di klien. Gaya peta diambil dari `NEARFIND_MAP_STYLE_URL` (default: OpenFreeMap Dark).

## Menjalankan di lokal

Butuh PHP 8.3+, Composer, dan Node 22+. Perintahnya ada di
**[CONTRIBUTING.md § Setup lokal](CONTRIBUTING.md#setup-lokal)**.

## Kontribusi

`main` diproteksi: semua perubahan lewat pull request, dan PR di-merge **manual** (squash) oleh
manusia setelah CI hijau - tidak ada job yang meng-arm auto-merge. Aturan lengkapnya - branch,
commit, alur merge, dan larangannya - ada di **[CONTRIBUTING.md](CONTRIBUTING.md)**.

## Lisensi

MIT.
