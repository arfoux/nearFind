// Shared helpers for the nearFind route ports.
//
// A port is a plain ES module the build copies verbatim into
// `dist/src/ports/<slug>.mjs`, so it must run inside the Worker exactly as it
// runs here. That means:
//   - Web platform APIs only (crypto.subtle, TextEncoder, btoa/atob) — no node:
//     builtins, no `laravel-workers` import (the package is NOT shipped into
//     dist/node_modules, only bcryptjs is).
//   - app code by RELATIVE path (`../kernel/hash.mjs` resolves to kernel/hash.mjs
//     in the repo and dist/kernel/hash.mjs in the bundle).

/** The session cookie PHP writes unencrypted (EncryptCookies exception). */
export const COOKIE_NAME = 'nearfind_seal';

/** Mirrors config/seal.php + app/Support/Seal/Sealer.php. */
export const PURPOSE = 'session';
export const CLOCK_TOLERANCE_MS = 60_000;
export const MAX_TOKEN_SIZE = 16_384;

export function str(value) {
  return value === null || value === undefined ? '' : String(value);
}

/** A 302 with the same cache headers Laravel's RedirectResponse carries. */
export function redirect(location, extraHeaders = {}) {
  return {
    status: 302,
    headers: { location, 'cache-control': 'no-cache, private', ...extraHeaders },
    body: '',
  };
}

/** The worker answers every verb a route declares, so a port guards its own. */
export function methodNotAllowed(allow) {
  return {
    status: 405,
    headers: { allow: allow.join(', '), 'content-type': 'text/plain; charset=utf-8' },
    body: 'Method Not Allowed',
  };
}

/**
 * Serve a prerendered page from the platform's static assets. A route with a
 * port is in `_routes.json` `include`, so its GET reaches the worker instead of
 * the asset layer — the port has to hand the page back itself.
 */
export async function serveStatic(env, path, contentType = 'text/html; charset=utf-8') {
  const assets = env && env.ASSETS;
  if (!assets || typeof assets.fetch !== 'function') return null;
  try {
    const res = await assets.fetch(new Request('https://assets.local' + path, { method: 'GET' }));
    if (!res || !res.ok) return null;
    return {
      status: 200,
      headers: { 'content-type': contentType, 'cache-control': 'no-cache, private' },
      body: await res.text(),
    };
  } catch {
    return null;
  }
}

/** `cookie()->forget($name)` — an already-expired cookie. */
export function forgetCookie(name) {
  return [
    `${name}=`,
    'expires=Thu, 01 Jan 1970 00:00:00 GMT',
    'Max-Age=0',
    'path=/',
  ].join('; ');
}

/** `(int) $ttlMs / 60_000`, rounded up — Laravel's `ceil($minutes)`. */
export function minutesOf(ttlMs) {
  return Math.ceil(ttlMs / 60_000);
}

export function base64UrlEncode(bytes) {
  let binary = '';
  for (let i = 0; i < bytes.length; i++) binary += String.fromCharCode(bytes[i]);
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

export function base64UrlDecode(value) {
  const text = str(value);
  if (text === '' || !/^[A-Za-z0-9_-]+$/.test(text) || text.length % 4 === 1) {
    throw new Error('the value is not valid base64url');
  }
  const padded = text.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - (text.length % 4)) % 4);
  const binary = atob(padded);
  const bytes = new Uint8Array(binary.length);
  for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
  return bytes;
}

/** `Sealer::durationToMilliseconds()` for `12h`, `30d`, `45m`, `30s`, `500ms`. */
export function durationToMilliseconds(duration) {
  if (typeof duration === 'number') return duration > 0 ? duration : 0;
  const m = /^(\d+)(ms|s|m|h|d)$/.exec(str(duration));
  if (!m) return 0;
  const scale = { ms: 1, s: 1000, m: 60_000, h: 3_600_000, d: 86_400_000 }[m[2]];
  return Number(m[1]) * scale;
}

/**
 * The keyring exactly as config/seal.php builds it: `SEAL_KEYS` (JSON object of
 * id -> base64url key) wins, then one `SEAL_KEY` under `SEAL_KEY_ID` (default
 * `local`).
 * @returns {{ keys: Record<string, string>, currentKeyId: string } | null}
 */
export function keyringFromEnv(env) {
  const source = env || {};
  const keys = {};

  if (typeof source.SEAL_KEYS === 'string' && source.SEAL_KEYS !== '') {
    try {
      const parsed = JSON.parse(source.SEAL_KEYS);
      if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
        for (const [id, value] of Object.entries(parsed)) {
          if (typeof value === 'string' && value !== '') keys[id] = value;
        }
      }
    } catch {
      // A malformed SEAL_KEYS leaves SEAL_KEY as the only source.
    }
  }

  const single = str(source.SEAL_KEY);
  if (single !== '') keys[str(source.SEAL_KEY_ID) || 'local'] = single;

  // Precedence: an explicit SEAL_CURRENT_KEY_ID, then the id that came with the
  // single SEAL_KEY, then the first key SEAL_KEYS declared. (`keys[single]` was
  // never a real lookup: `single` is the key MATERIAL, not a key id.)
  const currentKeyId =
    str(source.SEAL_CURRENT_KEY_ID) ||
    (single !== '' ? str(source.SEAL_KEY_ID) || 'local' : '') ||
    Object.keys(keys)[0] ||
    'local';
  if (Object.keys(keys).length === 0) return null;
  return { keys, currentKeyId };
}

/**
 * Mint a `stseal.v1.*` session token byte-compatible with `Sealer::seal()`:
 * AES-256-GCM, 12-byte IV, AAD `stseal.v1.<headerB64>`, ciphertext||tag, and no
 * padding on any segment. Inline (crypto.subtle) rather than the SDK because the
 * SDK is not shipped into dist/node_modules.
 */
export async function sealSessionToken(keyring, options) {
  const issuer = str(options.issuer) || 'nearFind';
  const audience = options.audience === null || options.audience === undefined || options.audience === ''
    ? null
    : String(options.audience);
  const keyBytes = keyring.keys[keyring.currentKeyId];
  if (keyBytes === undefined) throw new Error('unknown_kid');

  const key = base64UrlDecode(keyBytes);
  if (key.length !== 32) throw new Error('invalid_key');

  const now = Date.now();
  const header = { alg: 'A256GCM', kid: keyring.currentKeyId, pur: PURPOSE, iss: issuer };
  if (audience !== null) header.aud = audience;

  const encoder = new TextEncoder();
  const headerB64 = base64UrlEncode(encoder.encode(JSON.stringify(header)));
  const plaintext = encoder.encode(
    JSON.stringify({ iat: now, exp: now + options.ttlMs, data: options.data })
  );

  const iv = crypto.getRandomValues(new Uint8Array(12));
  const cryptoKey = await crypto.subtle.importKey('raw', key, { name: 'AES-GCM' }, false, ['encrypt']);
  const sealed = new Uint8Array(
    await crypto.subtle.encrypt(
      { name: 'AES-GCM', iv, additionalData: encoder.encode('stseal.v1.' + headerB64), tagLength: 128 },
      cryptoKey,
      plaintext
    )
  );

  const token = ['stseal', 'v1', headerB64, base64UrlEncode(iv), base64UrlEncode(sealed)].join('.');
  if (token.length > MAX_TOKEN_SIZE) throw new Error('token_too_large');
  return token;
}

/**
 * Verify a `stseal.v1.*` token and return its `data`, or null. The inverse of
 * `sealSessionToken` (and of PHP `Sealer::unseal()`); every rejection is null so
 * the caller fails closed.
 */
export async function unsealSessionToken(keyring, token, options = {}) {
  try {
    const parts = str(token).split('.');
    if (parts.length !== 5 || parts[0] !== 'stseal' || parts[1] !== 'v1') return null;
    if (str(token).length > MAX_TOKEN_SIZE) return null;

    const header = JSON.parse(new TextDecoder().decode(base64UrlDecode(parts[2])));
    if (!header || header.alg !== 'A256GCM' || header.pur !== PURPOSE) return null;
    if (str(header.iss) !== (str(options.issuer) || 'nearFind')) return null;
    const expectedAud = options.audience === null || options.audience === undefined || options.audience === ''
      ? null
      : String(options.audience);
    if ((header.aud ?? null) !== expectedAud) return null;

    const keyBytes = keyring.keys[header.kid];
    if (keyBytes === undefined) return null;
    const key = base64UrlDecode(keyBytes);
    if (key.length !== 32) return null;

    const plaintext = new Uint8Array(
      await crypto.subtle.decrypt(
        {
          name: 'AES-GCM',
          iv: base64UrlDecode(parts[3]),
          additionalData: new TextEncoder().encode('stseal.v1.' + parts[2]),
          tagLength: 128,
        },
        await crypto.subtle.importKey('raw', key, { name: 'AES-GCM' }, false, ['decrypt']),
        base64UrlDecode(parts[4])
      )
    );

    const payload = JSON.parse(new TextDecoder().decode(plaintext));
    if (!payload || typeof payload !== 'object') return null;
    const now = Date.now();
    if (typeof payload.exp !== 'number' || now > payload.exp) return null;
    if (typeof payload.iat === 'number' && now + CLOCK_TOLERANCE_MS < payload.iat) return null;
    return payload.data ?? null;
  } catch {
    return null;
  }
}

/** The session `Set-Cookie` value for a freshly minted token. */
export function sessionCookie(token, ttlMs, env) {
  const parts = [
    `${COOKIE_NAME}=${token}`,
    'Path=/',
    `Max-Age=${Math.floor(ttlMs / 1000)}`,
    'HttpOnly',
    'SameSite=Lax',
  ];
  if (env && typeof env.SEAL_COOKIE_DOMAIN === 'string' && env.SEAL_COOKIE_DOMAIN !== '') {
    parts.push(`Domain=${env.SEAL_COOKIE_DOMAIN}`);
  }
  return parts.join('; ');
}

/** Real client IP, preferring the platform header. */
export function clientIp(ctx) {
  const request = ctx.request;
  if (request && typeof request.ip === 'function') {
    const ip = request.ip();
    if (typeof ip === 'string' && ip !== '') return ip;
  }
  const headers = (request && typeof request.headers === 'function' ? request.headers() : null) || {};
  return headers['cf-connecting-ip'] || headers['x-forwarded-for'] || '';
}

/** `Str::lower(trim(...))`. */
export function normalizeEmail(value) {
  return str(value).trim().toLowerCase();
}

/** `Str::lower($email.'|'.$ip)` — the rate-limiter key. */
export function throttleKey(email, ip) {
  return normalizeEmail(email) + '|' + str(ip);
}

export function input(ctx) {
  const request = ctx.request;
  if (request && typeof request.input === 'function') {
    const all = request.input();
    if (all && typeof all === 'object' && !Array.isArray(all)) return all;
  }
  return {};
}

/** `session()->put('errors', ...)` + `_old_input`, then the referer redirect. */
export function redirectBack(ctx, errors, options = {}) {
  const session = ctx.session;
  if (session && typeof session.put === 'function') {
    const current = (typeof session.get === 'function' && session.get('errors')) || {};
    session.put('errors', { ...current, ...errors });
    if (options.withInput !== false) {
      const all = input(ctx);
      const keep = {};
      for (const key of Object.keys(all)) if (key !== 'password') keep[key] = all[key];
      session.put('_old_input', keep);
    }
  } else if (session && typeof session.flash === 'function') {
    session.flash('errors', errors);
  }

  const headers = (ctx.request && typeof ctx.request.headers === 'function' ? ctx.request.headers() : null) || {};
  return {
    status: 302,
    headers: {
      location: options.location || previousUrl(ctx, headers),
      'cache-control': 'no-cache, private',
    },
    body: '',
  };
}

/**
 * `url()->previous()` (vendor Illuminate/Routing/UrlGenerator.php:162-175): the
 * `Referer` header first, then the session's `_previous.url` (StartSession
 * stores the last GET), then the root. Laravel's `back()` and every validation
 * redirect go through it, so a port that answered `/` where PHP answers the page
 * the visitor came from is a user-visible divergence (AUDIT: it was).
 */
function previousUrl(ctx, headers) {
  const referer = headers.referer || headers.referrer;
  if (referer) return referer;
  const session = ctx.session;
  if (session && typeof session.get === 'function') {
    const url = session.get('_previous.url');
    if (typeof url === 'string' && url !== '') return url;
  }
  return '/';
}

/**
 * `Rule::unique('profiles', 'username')` minus the current row: true when no
 * OTHER profile owns the username. A DB without the table reads as "free".
 */
export async function usernameTaken(db, username, ignoreId = null) {
  if (!db || typeof db.prepare !== 'function') return false;
  try {
    const row = await db
      .prepare('select id from profiles where username = ? limit 1')
      .bind(String(username))
      .first();
    if (!row) return false;
    if (ignoreId === null || ignoreId === undefined) return true;
    return String(row.id ?? '') !== String(ignoreId);
  } catch {
    return false;
  }
}

/** One profile row by email, or null. */
export async function findProfileByEmail(db, email) {
  if (!db || typeof db.prepare !== 'function') return null;
  try {
    return (
      (await db.prepare('select * from profiles where email = ? limit 1').bind(normalizeEmail(email)).first()) ?? null
    );
  } catch {
    return null;
  }
}

/** `profile.notification_preferences` may be JSON text (D1) or an object (tests). */
export function parsePreferences(value) {
  if (value === null || value === undefined) return {};
  if (typeof value === 'object') return value;
  try {
    const parsed = JSON.parse(String(value));
    return parsed && typeof parsed === 'object' ? parsed : {};
  } catch {
    return {};
  }
}

/** `Profile::notificationSettings()` — the stored map over the defaults. */
export function notificationSettings(row) {
  return {
    need_updates: true,
    support_activity: true,
    weekly_digest: false,
    ...parsePreferences(row && row.notification_preferences),
  };
}

/** `'Y-m-d H:i:s'` in UTC — the format the migrations write for created_at/updated_at. */
export function sqlTimestamp(ms = Date.now()) {
  return new Date(ms).toISOString().slice(0, 19).replace('T', ' ');
}
