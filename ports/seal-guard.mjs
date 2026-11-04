// Guard port for the `seal` auth driver.
//
// `Auth::extend('seal', ...)` lives in app/Providers/AppServiceProvider.php as
// PHP app code the compiled rung cannot execute. This module answers that driver
// instead, decoding the same `stseal.v1.*` token app/Support/Seal/Sealer.php
// mints.
//
// laravel-workers guard-port contract:
//   export default { async user(ctx) { return user | null } }
//   ctx = { request, guard, driver, config, app: { config, db, models, env,
//           session, cookieJar } }
//
// Why the Wire format is inlined (ports/_lib.mjs) instead of the `stateless-seal`
// SDK: the build copies ONLY the runtime dependencies into dist/node_modules
// (bcryptjs today), so a port that imports the SDK is a bundle error at deploy
// time. `crypto.subtle` is the platform.
//
// Fail-closed: no cookie, a missing keyring, or any rejected token (expired,
// tampered, wrong purpose/issuer/audience, unknown kid) resolves to `null` — the
// request is simply unauthenticated. Nothing is logged; the token and the key
// material never leave this module.

import { keyringFromEnv, str, unsealSessionToken } from './_lib.mjs';

/** Cookie the PHP side writes unencrypted (EncryptCookies exception in bootstrap/app.php). */
export const COOKIE_NAME = 'nearfind_seal';

/**
 * The named cookie off the request, in every shape the rung presents one:
 * a Map-like or plain-object `request.cookies` (kernel/auth.mjs `requestCookie`)
 * or the compiled request's `cookie(name)` method (kernel/http.mjs).
 */
function readCookie(request, name) {
  if (!request || typeof request !== 'object') return null;

  const bag = request.cookies;
  if (bag !== null && bag !== undefined) {
    if (typeof bag.get === 'function') {
      const value = bag.get(name);
      if (typeof value === 'string' && value !== '') return value;
    } else if (typeof bag === 'object' && Object.prototype.hasOwnProperty.call(bag, name)) {
      const value = bag[name];
      if (typeof value === 'string' && value !== '') return value;
    }
  }

  if (typeof request.cookie === 'function') {
    const value = request.cookie(name, null);
    if (typeof value === 'string' && value !== '') return value;
  }

  return null;
}

/**
 * One keyring per distinct seal configuration, built on first use and kept for
 * the isolate's lifetime (module scope). `null` = this env cannot verify
 * anything (fail closed).
 */
const keyringCache = new Map();

function keyringFor(env) {
  const fingerprint = JSON.stringify([
    env && env.SEAL_KEYS,
    env && env.SEAL_KEY,
    env && env.SEAL_KEY_ID,
    env && env.SEAL_CURRENT_KEY_ID,
  ]);
  if (keyringCache.has(fingerprint)) return keyringCache.get(fingerprint);
  const keyring = keyringFromEnv(env || {});
  keyringCache.set(fingerprint, keyring);
  return keyring;
}

export default {
  async user(ctx) {
    try {
      const token = readCookie(ctx?.request, COOKIE_NAME);
      if (token === null) return null;

      const env = (ctx && ctx.app && ctx.app.env) || {};
      const keyring = keyringFor(env);
      if (!keyring) return null;

      const payload = await unsealSessionToken(keyring, token, {
        issuer: str(env.SEAL_ISSUER) || 'nearFind',
        audience: str(env.SEAL_AUDIENCE) || 'web',
      });
      if (!payload || typeof payload !== 'object') return null;

      const { email, name } = payload;
      if (typeof email !== 'string' || email === '' || typeof name !== 'string' || name === '') {
        return null;
      }

      return { email, name };
    } catch {
      // Fail closed and stay silent: a hostile token is "not authenticated",
      // never a 500 that could leak why (or the token itself).
      return null;
    }
  },
};
