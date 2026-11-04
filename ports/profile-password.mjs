// `PUT /profil/kata-sandi` — ProfileController::updatePassword().
//
// Not compiled: `$this->resolveProfile($request)`. The port validates the two
// fields, checks the current password against the same hash the login path uses,
// then stores `Hash::make()`'s bcrypt output.

import bcrypt from 'bcryptjs';
import { redirect, redirectBack, str } from './_lib.mjs';
import { profileForSession } from './profile-update.mjs';

const MESSAGES = {
  'current_password.required': 'Kata sandi saat ini wajib diisi.',
  'password.required': 'Kata sandi baru wajib diisi.',
  'password.min': 'Kata sandi baru minimal 8 karakter.',
  'password.confirmed': 'Konfirmasi kata sandi baru belum cocok.',
};

function validate(input) {
  const errors = {};
  const current = input.current_password;
  const password = input.password;
  const confirmation = input.password_confirmation;

  if (current === undefined || current === null || current === '') {
    errors.current_password = MESSAGES['current_password.required'];
  }
  if (password === undefined || password === null || password === '') errors.password = MESSAGES['password.required'];
  else if (str(password).length < 8) errors.password = MESSAGES['password.min'];
  else if (confirmation !== password) errors.password = MESSAGES['password.confirmed'];

  return errors;
}

export default async function handle(ctx, env) {
  const profile = await profileForSession(ctx);
  if (!profile) return redirect('/masuk');

  const body = (ctx.request && typeof ctx.request.input === 'function' ? ctx.request.input() : null) || {};
  const errors = validate(body);
  if (Object.keys(errors).length > 0) return redirectBack(ctx, errors);

  const storedHash = str(profile.password_hash) || str(env && env.NEARFIND_AUTH_PASSWORD_HASH);
  if (storedHash === '') {
    return redirectBack(ctx, { current_password: 'Kata sandi saat ini tidak cocok.' });
  }

  let matches = false;
  try {
    matches = await bcrypt.compare(str(body.current_password), storedHash);
  } catch {
    matches = false;
  }
  if (!matches) return redirectBack(ctx, { current_password: 'Kata sandi saat ini tidak cocok.' });

  let nextHash;
  try {
    nextHash = await bcrypt.hash(str(body.password), 12);
  } catch {
    return redirectBack(ctx, { password: 'Kata sandi baru gagal disimpan.' });
  }

  try {
    await ctx.db
      .prepare('update profiles set password_hash = ? where id = ?')
      .bind(nextHash, profile.id)
      .run();
  } catch {
    return redirectBack(ctx, { password: 'Kata sandi baru gagal disimpan.' });
  }

  if (ctx.session && typeof ctx.session.flash === 'function') {
    ctx.session.flash('status', 'Kata sandi berhasil diperbarui.');
  } else if (ctx.session && typeof ctx.session.put === 'function') {
    ctx.session.put('status', 'Kata sandi berhasil diperbarui.');
  }

  return redirect('/profil');
}
