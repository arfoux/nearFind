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

Branch head-nya dihapus otomatis - juga untuk PR yang di-merge bot. Jalur normalnya: job auto-merge menunggu sampai status PR-nya `MERGED`, lalu menghapus branch head-nya sendiri (`.github/workflows/auto-merge.yml`). Ini perlu karena merge oleh `GITHUB_TOKEN` tidak memicu `delete_branch_on_merge` GitHub. Kalau job itu menunggu lebih dari 30 menit atau penghapusannya gagal, sisanya disapu `.github/workflows/cleanup-branches.yml` - jadwal 15 menitnya best-effort punya GitHub, jadi sapu manual kalau perlu:

```bash
gh workflow run cleanup-branches.yml
```

## Yang perlu dihindari

- **Push ke `main`** — ditolak server.
- **`git add -A` / `git add .`** — stage hanya file yang kamu ubah.
- **Force-push, amend, atau rebase branch orang lain** — terutama yang sudah di-push.
- **Melanjutkan kerja di branch yang sudah di-merge** — branch head-nya dihapus otomatis; mulai branch baru dari `main` terbaru.
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
composer setup   # install dependensi, .env + APP_KEY, database/database.sqlite + migrate, build asset
composer dev     # server di 127.0.0.1:8000 + queue + vite sekaligus
```

Aplikasi memakai sqlite untuk session, cache, dan queue (lihat `.env.example`), jadi
`database/database.sqlite` wajib ada dan ter-migrate. File itu tidak ada di repo - `composer setup`
yang membuatnya (`touch` + `migrate`), supaya klon segar tidak berakhir dengan halaman 500.

Perintah yang dijalankan CI persis: `vendor/bin/pint --test --format agent` dan `php artisan test --compact`.
