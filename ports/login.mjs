// `POST /auth` — LoginController::store().
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

/** The `<!--lw-flash-->` slot `resources/views/auth/index.blade.php` leaves for the edge. */
const FLASH_MARKER = '<!--lw-flash-->';

/** The banner icon markup of `partials/icon`. */
function iconMarkup(name, iconClass) {
  return (
    '<svg class="' + iconClass + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"' +
    ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' +
    '<use href="#nf-' + name + '"></use></svg>'
  );
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/**
 * `GET /auth` is a prerendered asset, so the Blade `@error`/`session('status')`
 * blocks cannot see the POST's flash - the page was rendered at build time.
 * Fill the `<!--lw-flash-->` slot the view leaves for this, then consume the
 * flash, exactly the one-shot lifetime Laravel gives
 * `redirect()->withErrors()` / `redirect()->with('status')`.
 */
function injectFlash(ctx, html) {
  const session = ctx.session;
  if (!session || typeof session.get !== 'function') return html;

  const stored = session.get('errors');
  const messages = [];
  if (stored && typeof stored === 'object') {
    for (const value of Object.values(stored)) {
      if (typeof value === 'string' && value !== '') messages.push(value);
      else if (Array.isArray(value)) for (const item of value) if (typeof item === 'string' && item !== '') messages.push(item);
    }
  }
  const status = session.get('status');
  const old = session.get('_old_input');
  const email = old && typeof old === 'object' && typeof old.email === 'string' ? old.email : '';
  const errorKeys = stored && typeof stored === 'object' ? Object.keys(stored) : [];
  const registerMode =
    (old && typeof old === 'object' && Object.prototype.hasOwnProperty.call(old, 'name')) ||
    errorKeys.includes('name') ||
    errorKeys.includes('password_confirmation');

  let out = html;
  const statusBanner =
    typeof status === 'string' && status !== ''
      ? '<div class="mb-4 flex items-start gap-2.5 rounded-2xl bg-brand-50 px-3.5 py-3 text-[11.5px] font-medium text-brand-200 ring-1 ring-white/10">' +
        iconMarkup('check', 'mt-0.5 size-4 shrink-0') +
        '<span>' + escapeHtml(status) + '</span></div>'
      : '';
  const errorBanner =
    messages.length > 0
      ? '<div class="mb-4 flex items-start gap-2.5 rounded-2xl bg-brand-50/10 px-3.5 py-3 text-[11.5px] font-medium text-[#FF9B9B] ring-1 ring-[#FF9B9B]/15" role="alert">' +
        iconMarkup('flag', 'mt-0.5 size-4 shrink-0') +
        '<span>' + messages.map(escapeHtml).join('<br>') + '</span></div>'
      : '';
  if (statusBanner !== '' || errorBanner !== '') {
    out = out.replace(FLASH_MARKER, statusBanner + errorBanner);
  }
  if (registerMode) {
    // The static page ships on the login tab; a register POST that failed
    // validation must come back with its own tab open.
    out = out.replace('data-auth-initial="login"', 'data-auth-initial="register"');
  }
  if (email !== '') {
    out = out.replace(
      /(name="email"[^>]*?value=")[^"]*(")/g,
      (match, open, close) => open + escapeHtml(email) + close
    );
  }
  if ((statusBanner !== '' || errorBanner !== '' || email !== '' || registerMode) && typeof session.forget === 'function') {
    session.forget(['errors', '_old_input', 'status']);
  }
  return out;
}

export default async function handle(ctx, env) {
  const verb = String(ctx.method || '').toUpperCase();

  // GET /auth is a prerendered page. The route is in `_routes.json` include
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
    // port used to pass through (PHP answers /auth with 200, not a redirect).
    const page = await serveStatic(env, '/auth/index.html');
    if (!page) return methodNotAllowed(['GET', 'HEAD']);
    let token = ctx.session && typeof ctx.session.token === 'function' ? ctx.session.token() : null;
    if (!token && ctx.session && typeof ctx.session.regenerateToken === 'function') {
      ctx.session.regenerateToken();
      token = typeof ctx.session.token === 'function' ? ctx.session.token() : null;
    }
    if (token) {
      page.body = page.body.replace(/(name="_token"[^>]*?value=")[^"]*(")/g, '$1' + String(token) + '$2');
    }
    page.body = injectFlash(ctx, page.body);
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
  const configuredHash = str(env && env.NEARFIND_AUTH_PASSWORD_HASH);
  const profileHash = profile ? str(profile.password_hash) : '';

  // PHP (LoginController::store, :30-52): two ways in, one credential each.
  //   - the deployment credential: `NEARFIND_AUTH_EMAIL` (the config group the
  //     build does not dump) + `NEARFIND_AUTH_PASSWORD_HASH`; the email is half
  //     of it, so the row's hash can never shadow it;
  //   - a row created by /auth/daftar: its own email + hash, and only once the mailed
  //     link set `email_verified_at`. An unverified row answers the same message
  //     Laravel's MustVerifyEmail flow does, after a correct password.
  const passwordMatches = async (hash) => {
    if (hash === '') return false;
    try {
      return await bcrypt.compare(password, hash);
    } catch {
      return false;
    }
  };

  let accepted = false;
  if (configuredEmail !== '' && configuredHash !== '' && configuredEmail === email) {
    accepted = await passwordMatches(configuredHash);
  } else if (profileHash !== '' && await passwordMatches(profileHash)) {
    if (str(profile.email_verified_at) === '') {
      return redirectBack(ctx, {
        email: 'Email belum diverifikasi. Buka tautan verifikasi yang kami kirim ke ' + str(profile.email) + '.',
      });
    }
    accepted = true;
  }

  if (!accepted) {
    return redirectBack(ctx, { email: 'Email atau kata sandi tidak cocok.' });
  }
  await clear(ctx, key);

  const session = ctx.session;
  if (session && typeof session.regenerate === 'function') await session.regenerate();
  if (session && typeof session.put === 'function') {
    session.put('seal_email', email);
    session.put('seal_name', (profile && str(profile.name)) || str(env && env.NEARFIND_AUTH_NAME) || 'nearFind');
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
      name: (profile && str(profile.name)) || str(env.NEARFIND_AUTH_NAME) || 'nearFind',
    },
  });

  return redirect(successTarget(ctx, session), setSessionCookie(ctx, env, token, ttlMs));
}

/**
 * `redirect()->intended(route('home'))` (LoginController::store:75): the URL the
 * auth middleware stashed when it bounced the visitor to /auth wins, then the
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
