# Kontribusi

`main` di repo ini **diproteksi**: tidak ada yang bisa push langsung ke sana — termasuk owner. Semua perubahan lewat pull request, dan PR biasa **merge sendiri** setelah syaratnya terpenuhi.

## Alur singkat

```bash
git fetch origin && git switch main && git pull origin main
git switch -c feat/nama-singkat            # satu pekerjaan = satu branch
# ...kerja...
vendor/bin/pint --test                     # gaya kode (sama seperti CI)
php artisan test --compact                 # tes
git add <file>                             # sebutkan file; jangan `git add -A`
git commit -m "feat(map): ..."             # satu logika per commit
git push -u origin feat/nama-singkat
gh pr create --fill                        # atau lewat web
```

Setelah PR dibuka, `.github/workflows/auto-merge.yml` meng-arm auto-merge untuk PR itu. PR lalu merge sendiri begitu:

1. CI hijau — check `Lint, test, and build` (pint + `php artisan test`),
2. branch sudah up-to-date dengan `main`,
3. semua percakapan review diselesaikan.

Branch head-nya kemudian dihapus otomatis.

## Yang perlu dihindari

- **Push ke `main`** — ditolak server.
- **`git add -A` / `git add .`** — stage hanya file yang kamu ubah.
- **Force-push, amend, atau rebase branch orang lain** — terutama yang sudah di-push.
- **Menimpa perubahan orang lain untuk "menyelesaikan" konflik** — kalau file tercampur, berhenti dan tanya.
- Commit `.env` dan secrets, `node_modules`/`vendor`, serta hasil build (`dist/`, `.cache/`, `wrangler.jsonc`).

## Kalau `main` bergerak duluan

PR-mu jadi "behind" dan auto-merge menunggu. Sinkronkan lewat tombol **Update branch** di halaman PR, atau:

```bash
gh pr update-branch
```

## PR yang menyentuh `.github/workflows/`

Tidak di-arm otomatis. Perubahan konfigurasi CI wajib ditinjau manusia, jadi PR seperti itu harus di-merge manual.

## Review

Jumlah approval wajib saat ini `0`, jadi PR bisa merge tanpa reviewer. Untuk perubahan besar tetap minta satu orang membaca. Kalau ada percakapan review yang belum selesai, proteksi menahan merge sampai diselesaikan.

## Setup lokal

```bash
composer install
npm ci
cp .env.example .env && php artisan key:generate
npm run build
php artisan serve
```

Perintah yang dijalankan CI persis: `vendor/bin/pint --test --format agent` dan `php artisan test --compact`.
