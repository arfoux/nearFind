// `PUT /profil/notifikasi` — ProfileController::updateNotifications().
//
// The three booleans are stored as the `notification_preferences` JSON object,
// preserving the stored map's other members the way `$profile->update([...])`
// writes the whole attribute.

import { notificationSettings, parsePreferences, redirect, redirectBack } from './_lib.mjs';
import { profileForSession } from './profile-update.mjs';

function bool(value) {
  return value === true || value === '1' || value === 1 || value === 'on' || value === 'true';
}

export default async function handle(ctx) {
  const profile = await profileForSession(ctx);
  if (!profile) return redirect('/masuk');

  const body = (ctx.request && typeof ctx.request.input === 'function' ? ctx.request.input() : null) || {};

  const next = notificationSettings({
    notification_preferences: {
      ...parsePreferences(profile.notification_preferences),
      need_updates: bool(body.need_updates),
      support_activity: bool(body.support_activity),
      weekly_digest: bool(body.weekly_digest),
    },
  });

  try {
    await ctx.db
      .prepare('update profiles set notification_preferences = ? where id = ?')
      .bind(JSON.stringify(next), profile.id)
      .run();
  } catch {
    return redirectBack(ctx, {}, { withInput: false });
  }

  if (ctx.session && typeof ctx.session.flash === 'function') {
    ctx.session.flash('status', 'Pengaturan notifikasi disimpan.');
  } else if (ctx.session && typeof ctx.session.put === 'function') {
    ctx.session.put('status', 'Pengaturan notifikasi disimpan.');
  }

  return redirect('/profil');
}
