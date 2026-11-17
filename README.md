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
Hasil Nominatim diklasifikasikan sebagai daerah (jalan, desa, kota, kabupaten,
provinsi, negara) atau tempat seperti masjid. Keduanya bisa diklik di peta dan
membuka panel detail. Detail diambil dari Nominatim tanpa API key; jam buka
ditampilkan hanya jika tersedia dalam data OpenStreetMap.

Kategori dari tag OSM yang diizinkan dicerminkan ke kategori nearFind di
`config/needs.php`: tempat ibadah, sekolah, bank, ATM, kantor pos,
perpustakaan, kepolisian, taman, dan museum. Kategori ini memakai ikon yang
sudah ada di sprite, tanpa aset baru. Saat tempat dari OpenStreetMap disimpan,
formulir lokasi memilih kategori nearFind yang cocok bila kategorinya tersedia.

## Perilaku peta

Peta tidak menampilkan tanda silang di tengah. Titik tengah peta tetap dipakai
saat Ajukan Kebutuhan atau Tambah Tempat ditekan; logika pengambilan titik itu
tidak berubah. Kursor berada pada bentuk normal di atas peta maupun penanda,
dan hanya berubah menjadi `grabbing` selagi peta diseret.

Saat Overpass gagal, nearFind mencoba mirror Overpass sekunder dengan radius
lebih lebar bila perlu agar tempat OSM tetap muncul.

Setiap penanda, baik pengajuan warga, tempat tersimpan, tempat maupun daerah
dari OpenStreetMap, memuat label ringkas yang menguat saat hover atau fokus dan
membuka panel detail saat diklik. Label memuat judul dan baris sumber
(`Pengajuan warga`, `Tempat tersimpan`, atau `OpenStreetMap`). Pin memakai
ukuran tetap dalam piksel dan bentuk yang mengikuti kategori: rumah, perbaikan,
kuliner, ritel, kesehatan, ibadah, pendidikan, bank, ATM, pos, perpustakaan,
kepolisian, taman, dan museum, ditambah bentuk pin garis untuk data
OpenStreetMap tanpa kategori. Warna berasal dari warna kategori yang sudah ada;
tidak ada aset atau warna baru.

Tempat penting atau tonggak mendapat prioritas tampil pada penanda, sementara
kedekatan tetap mengurutkan hasil.

Pin pengajuan warga terisi, pin tempat tersimpan memiliki garis tepi lebih
kuat, dan pin OpenStreetMap lebih tenang dengan bentuk garis luar. Pratinjau
daerah mempertahankan garis putus-putus. Saat peta diperkecil, penanda yang
rapat disaring agar peta tetap terbaca. Secara bawaan, hasil pencarian
diurutkan dari yang terdekat dengan lokasi pengguna, lalu yang terdekat
dengan pusat peta yang terlihat. Batas fair-use tidak berubah.

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
