// `POST /edge/needs` — NeedController::store() for the compiled worker.
//
// The controller itself transpiles (it is plain query-builder work), but the
// route it sits on needs a session and the session comes from the `seal` guard
// port; the worker answers this path from here so the write, the lazy profile
// row, and the redirect are in one place. PHP keeps the same behaviour on
// `POST /needs`; both paths are validated with the same rules.
//
// The row is the first thing an unauthenticated visitor must not create: with
// no session the port answers 302 to /auth and writes nothing.

import bcrypt from 'bcryptjs';
import { findProfileByEmail, input, normalizeEmail, redirect, redirectBack, str } from './_lib.mjs';

const MESSAGES = {
  'title.required': 'Ceritakan kebutuhan yang ingin diajukan.',
  'title.min': 'Judul kebutuhan minimal 3 karakter.',
  'title.max': 'Judul kebutuhan maksimal 120 karakter.',
  'category.required': 'Pilih kategori kebutuhan.',
  'latitude.required': 'Tandai dulu titik lokasi kebutuhan di peta.',
  'latitude.numeric': 'Titik lokasi tidak valid.',
  'latitude.between': 'Titik lokasi di luar jangkauan peta.',
  'longitude.required': 'Tandai dulu titik lokasi kebutuhan di peta.',
  'longitude.numeric': 'Titik lokasi tidak valid.',
  'longitude.between': 'Titik lokasi di luar jangkauan peta.',
  'location.max': 'Patokan lokasi maksimal 120 karakter.',
  'description.max': 'Deskripsi kebutuhan maksimal 600 karakter.',
  'area.max': 'Nama wilayah maksimal 120 karakter.',
  'category.in': 'Pilih kategori yang tersedia.',
};

/** `config('needs.categories')` ids, minus the `all` pseudo-category. */
function categoryIds(env) {
  const ids = [];
  const categories = env && env.NEARFIND_CATEGORIES;
  if (typeof categories === 'string' && categories !== '') {
    try {
      const parsed = JSON.parse(categories);
      if (Array.isArray(parsed)) {
        for (const item of parsed) {
          if (item && typeof item.id === 'string' && item.id !== 'all') ids.push(item.id);
        }
      }
    } catch {
      // A malformed value leaves the list empty; the controller still checks it.
    }
  }
  return ids;
}

function validate(body, env) {
  const errors = {};
  const title = str(body.title).trim();
  const category = str(body.category);
  const location = str(body.location).trim();
  const description = str(body.description).trim();
  const area = str(body.area).trim();

  if (title === '') errors.title = MESSAGES['title.required'];
  else if (title.length < 3) errors.title = MESSAGES['title.min'];
  else if (title.length > 120) errors.title = MESSAGES['title.max'];

  if (category === '') errors.category = MESSAGES['category.required'];
  else if (categoryIds(env).length > 0 && !categoryIds(env).includes(category)) {
    errors.category = MESSAGES['category.in'];
  }

  // The pin is what puts a need on the map, so it is required.
  const lat = Number.parseFloat(str(body.latitude));
  const lng = Number.parseFloat(str(body.longitude));

  if (str(body.latitude) === '' || Number.isNaN(lat)) errors.latitude = MESSAGES['latitude.required'];
  else if (lat < -90 || lat > 90) errors.latitude = MESSAGES['latitude.between'];

  if (str(body.longitude) === '' || Number.isNaN(lng)) errors.longitude = MESSAGES['longitude.required'];
  else if (lng < -180 || lng > 180) errors.longitude = MESSAGES['longitude.between'];

  if (location.length > 120) errors.location = MESSAGES['location.max'];
  if (description.length > 600) errors.description = MESSAGES['description.max'];
  if (area.length > 120) errors.area = MESSAGES['area.max'];

  return errors;
}

/** A unique handle for a profile created on the fly (ProfileController's shape). */
async function uniqueUsername(db, email) {
  let base = normalizeEmail(email).split('@')[0].replace(/[^a-z0-9._-]/g, '').replace(/^[._-]+|[._-]+$/g, '');
  if (base === '') base = 'warga';
  base = base.slice(0, 24);

  let username = base;
  let suffix = 1;

  while (await usernameTaken(db, username)) {
    suffix += 1;
    username = base + suffix;
  }

  return username;
}

async function usernameTaken(db, username) {
  if (!db || typeof db.prepare !== 'function') return false;
  try {
    const row = await db.prepare('select id from profiles where username = ? limit 1').bind(username).first();
    return row !== null && row !== undefined;
  } catch {
    return false;
  }
}

export default async function handle(ctx, env) {
  const verb = String(ctx.method || '').toUpperCase();
  if (verb !== 'POST') {
    return { status: 405, headers: { allow: 'POST', 'content-type': 'text/plain; charset=utf-8' }, body: 'Method Not Allowed' };
  }

  // No session: the browser goes to the sign-in page and nothing is written.
  const email = str(ctx.session && typeof ctx.session.get === 'function' ? ctx.session.get('seal_email') : '');
  if (email === '') return redirect('/auth');

  const body = input(ctx);
  const errors = validate(body, env);
  if (Object.keys(errors).length > 0) return redirectBack(ctx, errors);

  const db = ctx.db;
  if (!db || typeof db.prepare !== 'function') return redirectBack(ctx, { title: 'Database tidak tersedia.' });

  let profile = await findProfileByEmail(db, email);
  if (!profile) {
    const name = str(ctx.session.get('seal_name')) || 'Warga nearFind';
    const username = await uniqueUsername(db, email);
    // An account created by registration has a hash; the deployment credential
    // does not, so a lazy row keeps an unusable one (`!` never verifies).
    const unusable = bcrypt.hashSync(Math.random().toString(36), 10).replace(/^\$2[ab]\$/, '$2y$');
    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');

    await db
      .prepare(
        'insert into profiles (email, name, username, password_hash, notification_preferences, created_at, updated_at) ' +
          'values (?, ?, ?, ?, ?, ?, ?)'
      )
      .bind(email, name, username, unusable, '{"need_updates":true,"support_activity":true,"weekly_digest":false}', now, now)
      .run();

    profile = await findProfileByEmail(db, email);
  }

  if (!profile) return redirectBack(ctx, { title: 'Profil tidak dapat dibuat.' });

  const title = str(body.title).trim();
  const location = str(body.location).trim();
  const description = str(body.description).trim();
  const area = str(body.area).trim();
  const now = new Date().toISOString().slice(0, 19).replace('T', ' ');

  await db
    .prepare(
      'insert into needs (profile_id, title, marker_label, category, location, description, area, latitude, longitude, supporters, opportunity, status, created_at, updated_at) ' +
        'values (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?)'
    )
    .bind(profile.id, title, title, str(body.category), location || null, description || null, area || null,
      Number.parseFloat(str(body.latitude)), Number.parseFloat(str(body.longitude)), 'aktif', now, now)
    .run();

  // Back to the map on the new pin, same as the compiled controller.
  const created = await db
    .prepare('select id from needs where profile_id = ? order by id desc limit 1')
    .bind(profile.id)
    .first();
  const newId = created && created.id ? created.id : null;

  if (ctx.session && typeof ctx.session.flash === 'function') {
    ctx.session.flash('status', 'Kebutuhan “' + title + '” berhasil diajukan dan sudah tampil di peta.');
  }

  return redirect(newId === null ? '/' : '/?need=' + newId);
}
