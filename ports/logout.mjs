// `POST /keluar` — LoginController::destroy().
//
// The controller does not compile because of `cookie()` alone (LW001 unknown
// function cookie at LoginController.php:84); every other statement is inside
// the supported subset. The port reproduces the three effects:
//   1. `$request->session()->invalidate()`    — drop the session, new id
//   2. `$request->session()->regenerateToken()` — new CSRF token
//   3. redirect to the login route with `status` flashed and the session cookie
//      expired (`cookie()->forget()`).
//
// The redirect target is the app's own `auth` route, so it is looked up in
// ctx.routeRegistry (the same registry the compiled rung builds its URLs from)
// instead of being hard-coded.

import { COOKIE_NAME, forgetCookie, redirect } from './_lib.mjs';

export default async function handle(ctx) {
  const session = ctx.session;

  // invalidate() is async in this kernel (D1-backed): it migrates the session
  // to a fresh id and flushes the attributes. Then the CSRF token rotates.
  if (session && typeof session.invalidate === 'function') await session.invalidate();
  else if (session && typeof session.flush === 'function') session.flush();
  if (session && typeof session.regenerateToken === 'function') session.regenerateToken();

  if (session && typeof session.flash === 'function') {
    session.flash('status', 'Kamu sudah keluar dari akunmu.');
  } else if (session && typeof session.put === 'function') {
    session.put('status', 'Kamu sudah keluar dari akunmu.');
  }

  let location = '/';
  try {
    const registry = ctx.routeRegistry;
    if (registry && typeof registry.route === 'function') {
      const uri = registry.route('auth');
      if (typeof uri === 'string' && uri !== '') location = uri;
    }
  } catch {
    // A registry without the named route keeps the `/` fallback rather than
    // turning a logout into a 500.
  }

  // Laravel's `CookieJar::forget()` MAKES the expired cookie but does not queue
  // it (vendor Illuminate/Cookie/CookieJar.php:99-102) — the caller queues it,
  // and only the jar's queue reaches the response.
  const jar = ctx.cookieJar;
  if (jar && typeof jar.forget === 'function' && typeof jar.queue === 'function') {
    jar.queue(jar.forget(COOKIE_NAME));
    return redirect(location);
  }
  return redirect(location, {
    'set-cookie': forgetCookie(COOKIE_NAME),
  });
}
