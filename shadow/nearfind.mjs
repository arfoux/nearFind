// nearFind/shadow/nearfind.mjs — the profile flow, PHP vs the compiled worker.
//
// Run from the laravel-workers checkout:
//   node shadow/run.mjs --app <this repo> --scenarios <this file> --build
//
// The credential email is the app's configured one: the worker reads it from
// `.dev.vars` (what `wrangler dev` passes), PHP from `.env` — the two files hold
// the same local credential. The profile row is seeded by the scenario, so the
// password is the one below (the profile row's hash wins on both sides).
import fs from 'node:fs';
import bcrypt from 'bcryptjs';

const vars = fs.readFileSync(new URL('../.dev.vars', import.meta.url), 'utf8');
const read = (name) => {
  const m = vars.match(new RegExp('^\\s*' + name + '\\s*=\\s*"?([^"\\r\\n]+)"?', 'm'));
  return m ? m[1] : '';
};

const EMAIL = read('NEARFIND_AUTH_EMAIL');
const PASSWORD = 'rahasia123';
// PHP's BcryptHasher accepts only the `$2y$` variant (PHP 8.3 answers
// `algoName=unknown` for `$2b$`, which bcryptjs writes). The digests are identical
// for an ASCII password, so the marker is rewritten — the scenario stays
// self-contained (override with NEARFIND_SEED_HASH for a PHP-generated hash).
const HASH =
  process.env.NEARFIND_SEED_HASH || bcrypt.hashSync(PASSWORD, 10).replace(/^\$2[ab]\$/, '$2y$');

export default {
  name: 'nearfind',
  env: {
    SEAL_KEY_ID: read('SEAL_KEY_ID'),
    SEAL_KEY: read('SEAL_KEY'),
    NEARFIND_AUTH_EMAIL: EMAIL,
  },
  fixtures: [
    {
      sql:
        "INSERT INTO profiles (id, email, name, username, phone, bio, password_hash, notification_preferences, created_at, updated_at) " +
        "VALUES (1, '" + EMAIL + "', 'Warga Uji', 'warga_uji', '081234567890', 'Warga RW 05 yang aktif.', '" + HASH + "', " +
        "'{\"need_updates\":true,\"support_activity\":true,\"weekly_digest\":false}', '2026-10-20 08:00:00', '2026-10-20 08:00:00')",
    },
    {
      sql:
        "INSERT INTO needs (id, profile_id, title, category, location, status, created_at, updated_at) " +
        "VALUES (1, 1, 'Perbaikan lampu gang 3', 'infrastruktur', 'Gang 3', 'aktif', '2026-10-21 09:00:00', '2026-10-21 09:00:00')",
    },
    {
      sql:
        "INSERT INTO need_supports (id, need_id, profile_id, created_at, updated_at) " +
        "VALUES (1, 1, 1, '2026-10-22 10:00:00', '2026-10-22 10:00:00')",
    },
    {
      sql:
        "INSERT INTO reports (id, profile_id, need_id, need_title, category, reason, status, created_at, updated_at) " +
        "VALUES (1, 1, 1, 'Perbaikan lampu gang 3', 'infrastruktur', 'Lampu sudah menyala kembali.', 'selesai', '2026-10-23 11:00:00', '2026-10-23 11:00:00')",
    },
  ],
  steps: [
    { label: 'GET /masuk (guest, the snapshot page)', method: 'GET', uri: '/masuk', jar: true },
    {
      label: 'POST /masuk (authenticates)',
      method: 'POST',
      uri: '/masuk',
      jar: true,
      form: { _token: '@token', email: EMAIL, password: PASSWORD },
    },
    { label: 'GET /profil (compiled page)', method: 'GET', uri: '/profil', jar: true, expectBody: 'Perbaikan lampu gang 3' },
    {
      label: 'PUT /profil (update the row)',
      method: 'PUT',
      uri: '/profil',
      jar: true,
      form: {
        _token: '@token',
        _form: 'edit-profile',
        name: 'Warga Uji',
        username: 'warga_uji',
        phone: '081234567890',
        bio: 'Warga RW 05 yang aktif.',
      },
    },
    { label: 'GET /profil (after the update)', method: 'GET', uri: '/profil', jar: true, expectBody: 'Profil berhasil diperbarui.' },
  ],
};
