// `POST /profil` — ProfileController::update(): the profile fields, minus the
// avatar upload (which needs the multipart/storage path the compiled rung keeps
// as a port; the field rules and the profile write are here).
//
// The controller does not compile because of `Rule::unique(...)` and the
// `$request->validate()` call shape; the port reproduces the same rules,
// messages, and write.

import { findProfileByEmail, normalizeEmail, redirect, redirectBack, usernameTaken } from './_lib.mjs';

const MESSAGES = {
  'name.required': 'Nama lengkap wajib diisi.',
  'name.max': 'Nama lengkap maksimal 100 karakter.',
  'username.max': 'Username maksimal 30 karakter.',
  'username.regex': 'Username hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda hubung.',
  'username.unique': 'Username ini sudah dipakai.',
  'phone.max': 'Nomor telepon terlalu panjang.',
  'phone.regex': 'Format nomor telepon tidak valid.',
  'bio.max': 'Bio maksimal 300 karakter.',
  'avatar.image': 'Foto profil harus berupa gambar.',
  'avatar.mimes': 'Foto profil harus berformat JPG, PNG, atau WEBP.',
  'avatar.max': 'Ukuran foto profil maksimal 2 MB.',
};

/** The session email the login port stored; `resolveProfile()` needs it. */
export function sessionEmail(ctx) {
  const session = ctx.session;
  if (!session || typeof session.get !== 'function') return '';
  return normalizeEmail(session.get('seal_email'));
}

/** The Profile row for the signed-in session, or null when there is none. */
export async function profileForSession(ctx) {
  const email = sessionEmail(ctx);
  if (email === '') return null;
  return findProfileByEmail(ctx.db, email);
}

async function validate(ctx, input, profile) {
  const errors = {};
  const name = input.name;
  const username = input.username;
  const phone = input.phone;
  const bio = input.bio;

  if (name === undefined || name === null || String(name).trim() === '') errors.name = MESSAGES['name.required'];
  else if (String(name).length > 100) errors.name = MESSAGES['name.max'];

  if (username !== undefined && username !== null && String(username) !== '') {
    if (String(username).length > 30) errors.username = MESSAGES['username.max'];
    else if (!/^[a-zA-Z0-9._-]+$/.test(String(username))) errors.username = MESSAGES['username.regex'];
    else if (await usernameTaken(ctx.db, String(username), profile ? profile.id : null)) {
      errors.username = MESSAGES['username.unique'];
    }
  }

  if (phone !== undefined && phone !== null && String(phone) !== '') {
    if (String(phone).length > 25) errors.phone = MESSAGES['phone.max'];
    else if (!/^[0-9+()\-\s]+$/.test(String(phone))) errors.phone = MESSAGES['phone.regex'];
  }

  if (bio !== undefined && bio !== null && String(bio).length > 300) errors.bio = MESSAGES['bio.max'];

  return errors;
}

export default async function handle(ctx) {
  // The route declares GET+HEAD+PUT and the worker answers every declared verb.
  // GET is the Blade page this edge does not render (registry.json cannot
  // `ignore` the route: that would drop the PUT handler too, and the build now
  // fails on the conflict). Answer it honestly instead of dispatching a page
  // request into the update path — which would flash and redirect to itself.
  const verb = String(ctx.method || '').toUpperCase();
  if (verb === 'GET' || verb === 'HEAD') {
    return {
      status: 404,
      headers: { allow: 'PUT', 'content-type': 'text/plain; charset=utf-8' },
      body: 'GET /profil is not served on the edge (the profile page is a Blade component view).',
    };
  }
  if (verb !== 'PUT') {
    return { status: 405, headers: { allow: 'PUT', 'content-type': 'text/plain; charset=utf-8' }, body: 'Method Not Allowed' };
  }

  const profile = await profileForSession(ctx);
  if (!profile) {
    // `auth:seal` guards this route in PHP; a session without a profile row has
    // nothing to update, and answering 302 keeps the browser flow honest.
    return redirect('/masuk');
  }

  const body = (ctx.request && typeof ctx.request.input === 'function' ? ctx.request.input() : null) || {};
  const errors = await validate(ctx, body, profile);
  if (Object.keys(errors).length > 0) return redirectBack(ctx, errors);

  const updates = {};
  for (const key of ['name', 'username', 'phone', 'bio']) {
    if (Object.prototype.hasOwnProperty.call(body, key)) updates[key] = body[key] === '' ? null : body[key];
  }

  const columns = Object.keys(updates);
  if (columns.length > 0) {
    const assignments = columns.map((c) => `${c} = ?`).join(', ');
    const values = columns.map((c) => updates[c]);
    try {
      await ctx.db
        .prepare(`update profiles set ${assignments} where id = ?`)
        .bind(...values, profile.id)
        .run();
    } catch {
      // A write the edge cannot complete must not silently answer "saved".
      return redirectBack(ctx, { name: 'Profil gagal disimpan.' });
    }
  }

  const session = ctx.session;
  if (session && typeof session.flash === 'function') session.flash('status', 'Profil berhasil diperbarui.');
  else if (session && typeof session.put === 'function') session.put('status', 'Profil berhasil diperbarui.');

  return redirect('/profil');
}
