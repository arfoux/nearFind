# nearFind

[![ci](https://github.com/arfoux/nearFind/actions/workflows/ci.yml/badge.svg)](https://github.com/arfoux/nearFind/actions/workflows/ci.yml) [![verdict: runs on the edge](https://img.shields.io/badge/verdict-RUNS%20ON%20THE%20EDGE-1FA37C)](https://github.com/arfoux/laravel-workers/blob/main/docs/CI.md) [![license: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Aplikasi peta: daftar kebutuhan (`config/needs.php`) per kategori ditampilkan di atas peta
[MapLibre GL](https://maplibre.org/). Laravel 13 (PHP 8.3+) di backend, Vite + Tailwind di sisi
klien. Gaya peta diambil dari `NEARFIND_MAP_STYLE_URL` (default: OpenFreeMap Positron).

## Menjalankan di lokal

Butuh PHP 8.3+, Composer, dan Node 22+. Perintahnya ada di
**[CONTRIBUTING.md § Setup lokal](CONTRIBUTING.md#setup-lokal)**.

## Kontribusi

`main` diproteksi: semua perubahan lewat pull request, dan PR biasa merge sendiri begitu CI hijau.
Aturan lengkapnya - branch, commit, alur merge, dan larangannya - ada di
**[CONTRIBUTING.md](CONTRIBUTING.md)**.

## Lisensi

MIT.
