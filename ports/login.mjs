// `POST /masuk` — LoginController::store().
//
// Not compiled: the constructor injects the app service App\Support\Seal\Sealer
// and the body calls `Sealer::durationToMilliseconds()` + `cookie()`. The port
// reproduces the flow against ctx.db (D1), ctx.cache (rate limiter) and inline
// AES-256-GCM, and mints exactly the cookie the guard port re-reads.
//
// Known, deliberate divergence: the credential email comes from
// `env.NEARFIND_AUTH_EMAIL` (config('seal.auth.email')) — the config dump strips
// credentials from dist, so the port reads the binding directly. Unset means the
// profile row's password_hash is the only way in.

import bcrypt from 'bcryptjs';
import {
  COOKIE_NAME,
  clientIp,
  durationToMilliseconds,
  findProfileByEmail,
  forgetCookie,
  keyringFromEnv,
  methodNotAllowed,
  minutesOf,
  normalizeEmail,
  redirect,
  redirectBack,
  sealSessionToken,
  serveStatic,
  sessionCookie,
  str,
  throttleKey,
} from './_lib.mjs';

const RATE_LIMIT_MAX = 5;
const RATE_LIMIT_DECAY_MS = 60_000;

const MESSAGES = {
  'email.required': 'Email wajib diisi.',
  'email.email': 'Format email tidak valid.',
  'email.max': 'Email terlalu panjang.',
  'password.required': 'Kata sandi wajib diisi.',
};

/** `LoginRequest::rules()` + `messages()`. */
function validate(input) {
  const errors = {};
  const email = input.email;
  const password = input.password;

  if (email === undefined || email === null || email === '') errors.email = MESSAGES['email.required'];
  else if (String(email).length > 255) errors.email = MESSAGES['email.max'];
  else if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(String(email))) errors.email = MESSAGES['email.email'];

  if (password === undefined || password === null || password === '') errors.password = MESSAGES['password.required'];

  return errors;
}

/** `RateLimiter::attempts($key)` / `tooManyAttempts($key, 5)` / `availableIn`. */
async function attempts(ctx, key) {
  const cache = ctx.cache;
  if (!cache || typeof cache.get !== 'function') return { tooMany: false, availableIn: 0 };

  const timerKey = key + ':timer';
  const expiresAt = Number((await cache.get(timerKey)) || 0);
  if (!expiresAt || Date.now() >= expiresAt) {
    // The window elapsed: Laravel's `clear()` on the next hit.
    if (typeof cache.forget === 'function') await cache.forget(key);
    return { tooMany: false, availableIn: 0, expiresAt: 0 };
  }

  const hits = Number((await cache.get(key)) || 0);
  return {
    tooMany: hits >= RATE_LIMIT_MAX,
    availableIn: Math.max(1, Math.ceil((expiresAt - Date.now()) / 1000)),
    expiresAt,
  };
}

/** `RateLimiter::hit($key, $decaySeconds)` — every attempt counts. */
async function hit(ctx, key, state) {
  const cache = ctx.cache;
  if (!cache || typeof cache.put !== 'function') return;
  const expiresAt = state && state.expiresAt ? state.expiresAt : Date.now() + RATE_LIMIT_DECAY_MS;
  const ttl = Math.max(1, Math.ceil((expiresAt - Date.now()) / 1000));
  if (!state || !state.expiresAt) await cache.put(key + ':timer', expiresAt, ttl);
  const hits = Number((await cache.get(key)) || 0) + 1;
  await cache.put(key, hits, ttl);
}

/** `RateLimiter::clear($key)` — the successful login forgets the failures. */
async function clear(ctx, key) {
  const cache = ctx.cache;
  if (!cache || typeof cache.forget !== 'function') return;
  await cache.forget(key);
  await cache.forget(key + ':timer');
}

export default async function handle(ctx, env) {
  const verb = String(ctx.method || '').toUpperCase();

  // GET /masuk is a prerendered page. The route is in `_routes.json` include
  // because POST is a port, so the asset layer never sees the GET: the port
  // answers it from the ASSETS binding, exactly the bytes the build wrote.
  //
  // One thing those bytes CANNOT carry is a usable CSRF token: the page was
  // rendered at build time, so its `_token` belongs to the build session and
  // every POST from it answers 419 (verified against the built worker). The
  // live session's token is written into the markup instead.
  if (verb === 'GET' || verb === 'HEAD') {
    // Fetch the FILE, not the directory: the asset layer's auto-trailing-slash
    // handling answers a directory request with a 307 to the same path, which the
    // port used to pass through (PHP answers /masuk with 200, not a redirect).
    const page = await serveStatic(env, '/masuk/index.html');
    if (!page) return methodNotAllowed(['GET', 'HEAD']);
    let token = ctx.session && typeof ctx.session.token === 'function' ? ctx.session.token() : null;
    if (!token && ctx.session && typeof ctx.session.regenerateToken === 'function') {
      ctx.session.regenerateToken();
      token = typeof ctx.session.token === 'function' ? ctx.session.token() : null;
    }
    if (token) {
      page.body = page.body.replace(/(name="_token"[^>]*?value=")[^"]*(")/g, '$1' + String(token) + '$2');
    }
    return page;
  }
  if (verb !== 'POST') return methodNotAllowed(['GET', 'HEAD', 'POST']);

  const body = (ctx.request && typeof ctx.request.input === 'function' ? ctx.request.input() : null) || {};
  const email = normalizeEmail(body.email);
  const password = str(body.password);
  const remember =
    body.remember === true || body.remember === '1' || body.remember === 'on' || body.remember === 'true';
  const key = throttleKey(email, clientIp(ctx));

  const errors = validate(body);
  if (Object.keys(errors).length > 0) return redirectBack(ctx, errors);

  const limit = await attempts(ctx, key);
  if (limit.tooMany) {
    return redirectBack(ctx, {
      email: `Terlalu banyak percobaan masuk. Coba lagi dalam ${limit.availableIn} detik.`,
    });
  }
  // LoginRequest::ensureIsNotRateLimited() hits BEFORE Authenticate: every
  // attempt counts, successful or not. A successful login clears the key.
  await hit(ctx, key, limit);

  const profile = await findProfileByEmail(ctx.db, email);
  const configuredEmail = normalizeEmail(str(env && env.NEARFIND_AUTH_EMAIL));
  const storedHash = (profile && str(profile.password_hash)) || str(env && env.NEARFIND_AUTH_PASSWORD_HASH);

  // PHP (LoginController::store, :32-37):
  //   $valid = $configuredEmail !== ''
  //         && $passwordHash !== ''
  //         && hash_equals($configuredEmail, $request->email())
  //         && Hash::check($password, $passwordHash);
  // The configured email is a REQUIRED half of the credential. An earlier
  // version also accepted "profile row has a hash and the env email is unset",
  // which PHP never allows — that path is removed: unset = nobody logs in.
  let accepted = false;
  if (configuredEmail !== '' && storedHash !== '' && configuredEmail === email) {
    try {
      accepted = await bcrypt.compare(password, storedHash);
    } catch {
      accepted = false;
    }
  }

  if (!accepted) {
    return redirectBack(ctx, { email: 'Email atau kata sandi tidak cocok.' });
  }
  await clear(ctx, key);

  const session = ctx.session;
  if (session && typeof session.regenerate === 'function') await session.regenerate();
  if (session && typeof session.put === 'function') {
    session.put('seal_email', email);
    session.put('seal_name', (profile && str(profile.name)) || str(env && env.NEARFIND_AUTH_NAME) || 'Warga nearFind');
  }

  const ttlMs =
    durationToMilliseconds(remember ? str(env && env.SEAL_REMEMBER_TTL) || '30d' : str(env && env.SEAL_SESSION_TTL) || '12h') ||
    durationToMilliseconds('12h');

  const keyring = keyringFromEnv(env);
  if (!keyring) {
    // Without a keyring the guard port cannot verify anything: fail loud rather
    // than hand the browser a cookie that authenticates nothing.
    return redirectBack(ctx, { email: 'Konfigurasi sesi tidak lengkap.' });
  }

  const token = await sealSessionToken(keyring, {
    issuer: str(env.SEAL_ISSUER) || 'nearFind',
    audience: str(env.SEAL_AUDIENCE) || 'web',
    ttlMs,
    data: {
      email,
      name: (profile && str(profile.name)) || str(env.NEARFIND_AUTH_NAME) || 'Warga nearFind',
    },
  });

  return redirect(successTarget(ctx, session), setSessionCookie(ctx, env, token, ttlMs));
}

/**
 * `redirect()->intended(route('home'))` (LoginController::store:75): the URL the
 * auth middleware stashed when it bounced the visitor to /masuk wins, then the
 * home route. The stashed value is a FULL url (kernel/http.mjs:1236), which is
 * what Laravel's `intended()` returns as well.
 */
function successTarget(ctx, session) {
  let target = '/';
  try {
    const registry = ctx.routeRegistry;
    if (registry && typeof registry.route === 'function') {
      const home = registry.route('home');
      if (typeof home === 'string' && home !== '') target = home;
    }
  } catch {
    // No registry: `/` is the home route of this app.
  }

  if (session && typeof session.get === 'function') {
    const intended = session.get('url.intended');
    if (typeof intended === 'string' && intended !== '') {
      if (typeof session.forget === 'function') session.forget('url.intended');
      else if (typeof session.put === 'function') session.put('url.intended', null);
      return intended;
    }
  }

  return target;
}

/**
 * The session cookie. Laravel's seam is the CookieJar (queued cookies are added
 * to the response by AddQueuedCookiesToResponse); the kernel drops a raw
 * `set-cookie` header, so the jar is the only path that survives. A plain unit
 * ctx has no jar — then the header is the best that can be done.
 */
function setSessionCookie(ctx, env, token, ttlMs) {
  const jar = ctx.cookieJar;
  if (jar && typeof jar.queue === 'function') {
    jar.queue(
      COOKIE_NAME,
      token,
      minutesOf(ttlMs),
      '/',
      str(env && env.SEAL_COOKIE_DOMAIN) || null,
      false, // secure: session.secure decides
      true, // httpOnly
      true, // raw: the token is base64url, never URL-encode it
      'lax'
    );
    return null;
  }
  return { 'set-cookie': sessionCookie(token, ttlMs, env) };
}

export { forgetCookie, RATE_LIMIT_MAX };
