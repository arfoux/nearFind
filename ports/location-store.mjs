// `POST /locations` and `POST /edge/locations`: LocationController::store()
// for the compiled worker. The controller is plain query-builder work, but the
// route needs the seal session, so the worker answers it from here. PHP keeps
// the same behaviour on `/locations`; both paths share the JSON row shape.

import { input, normalizeEmail, redirect, redirectBack, sqlTimestamp, str } from './_lib.mjs';

const DEDUP_RADIUS_METERS = 100;

const MESSAGES = {
  'name.required': 'Nama tempat wajib diisi.',
  'name.max': 'Nama tempat maksimal 120 karakter.',
  'category.required': 'Pilih kategori tempat.',
  'category.in': 'Pilih kategori yang tersedia.',
  'latitude.required': 'Tandai dulu titik lokasi di peta.',
  'latitude.numeric': 'Titik lokasi tidak valid.',
  'latitude.between': 'Titik lokasi di luar jangkauan peta.',
  'longitude.required': 'Tandai dulu titik lokasi di peta.',
  'longitude.numeric': 'Titik lokasi tidak valid.',
  'longitude.between': 'Titik lokasi di luar jangkauan peta.',
  'address.max': 'Alamat maksimal 255 karakter.',
  'area.max': 'Nama wilayah maksimal 120 karakter.',
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

function pick(body, longName, alias) {
  const value = body[longName];
  if (value !== undefined && value !== null && str(value) !== '') return value;
  return body[alias];
}

function validate(body, env) {
  const errors = {};
  const name = str(body.name).trim();
  const category = str(body.category);
  const address = str(body.address).trim();
  const area = str(body.area).trim();

  if (name === '') errors.name = MESSAGES['name.required'];
  else if (name.length > 120) errors.name = MESSAGES['name.max'];

  if (category === '') errors.category = MESSAGES['category.required'];
  else if (categoryIds(env).length > 0 && !categoryIds(env).includes(category)) {
    errors.category = MESSAGES['category.in'];
  }

  const lat = Number.parseFloat(str(pick(body, 'latitude', 'lat')));
  const lng = Number.parseFloat(str(pick(body, 'longitude', 'lng')));

  if (str(pick(body, 'latitude', 'lat')) === '' || Number.isNaN(lat)) errors.latitude = MESSAGES['latitude.required'];
  else if (lat < -90 || lat > 90) errors.latitude = MESSAGES['latitude.between'];

  if (str(pick(body, 'longitude', 'lng')) === '' || Number.isNaN(lng)) errors.longitude = MESSAGES['longitude.required'];
  else if (lng < -180 || lng > 180) errors.longitude = MESSAGES['longitude.between'];

  if (address.length > 255) errors.address = MESSAGES['address.max'];
  if (area.length > 120) errors.area = MESSAGES['area.max'];

  return errors;
}

function normalizeName(value) {
  return str(value).trim().replace(/\s+/gu, ' ').toLowerCase();
}

function distanceMeters(lat1, lng1, lat2, lng2) {
  const toRad = (degrees) => (degrees * Math.PI) / 180;
  const radius = 6_371_000;
  const dLat = toRad(lat2 - lat1);
  const dLng = toRad(lng2 - lng1);
  const a =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  return radius * c;
}

async function rowsForName(db, normalized) {
  const result = await db.prepare('select * from locations where lower(name) = ?').bind(normalized).all();
  if (Array.isArray(result)) return result;
  return result && Array.isArray(result.results) ? result.results : [];
}

/** Exactly the JSON shape HomeController puts in the data island. */
function present(row) {
  return {
    id: String(row.id),
    name: str(row.name),
    category: str(row.category),
    address: str(row.address ?? ''),
    area: str(row.area ?? ''),
    latitude: Number(row.latitude),
    longitude: Number(row.longitude),
    source: str(row.source || 'warga'),
    created_at: str(row.created_at ?? ''),
  };
}

function wantsJson(ctx) {
  const request = ctx.request;
  if (!request || typeof request.headers !== 'function') return false;
  const headers = request.headers() || {};
  const accept = str(headers.accept || headers.Accept);
  const requestedWith = str(headers['x-requested-with'] || headers['X-Requested-With']).toLowerCase();
  return accept.includes('application/json') || requestedWith === 'xmlhttprequest';
}

function jsonResponse(status, payload) {
  return {
    status,
    headers: { 'content-type': 'application/json; charset=utf-8', 'cache-control': 'no-cache, private' },
    body: JSON.stringify(payload),
  };
}

function answer(ctx, row, reused) {
  const message = reused
    ? 'Tempat “' + str(row.name) + '” sudah tersimpan di titik ini.'
    : 'Tempat “' + str(row.name) + '” berhasil disimpan dan sudah tampil di peta.';
  const target = '/?location=' + encodeURIComponent(String(row.id));

  if (wantsJson(ctx)) {
    return jsonResponse(200, { location: present(row), status: message, redirect: target });
  }

  if (ctx.session && typeof ctx.session.flash === 'function') ctx.session.flash('status', message);
  return redirect(target);
}

export default async function handle(ctx, env) {
  const verb = String(ctx.method || '').toUpperCase();
  if (verb !== 'POST') {
    return { status: 405, headers: { allow: 'POST', 'content-type': 'text/plain; charset=utf-8' }, body: 'Method Not Allowed' };
  }

  const session = ctx.session;
  const email = normalizeEmail(session && typeof session.get === 'function' ? session.get('seal_email') : '');
  if (email === '') {
    return wantsJson(ctx)
      ? jsonResponse(401, { message: 'Masuk untuk menyimpan tempat.' })
      : redirect('/auth');
  }

  const body = input(ctx);
  const errors = validate(body, env);
  if (Object.keys(errors).length > 0) {
    return wantsJson(ctx) ? jsonResponse(422, { errors }) : redirectBack(ctx, errors);
  }

  const db = ctx.db;
  if (!db || typeof db.prepare !== 'function') {
    return wantsJson(ctx)
      ? jsonResponse(503, { message: 'Database tidak tersedia.' })
      : redirectBack(ctx, { name: 'Database tidak tersedia.' });
  }

  const name = str(body.name).trim();
  const normalized = normalizeName(name);
  const latitude = Number.parseFloat(str(pick(body, 'latitude', 'lat')));
  const longitude = Number.parseFloat(str(pick(body, 'longitude', 'lng')));

  for (const existing of await rowsForName(db, normalized)) {
    if (distanceMeters(Number(existing.latitude), Number(existing.longitude), latitude, longitude) <= DEDUP_RADIUS_METERS) {
      return answer(ctx, existing, true);
    }
  }

  const address = str(body.address).trim();
  const area = str(body.area).trim();
  const now = sqlTimestamp();

  await db
    .prepare(
      'insert into locations (name, category, address, area, latitude, longitude, source, author_email, created_at, updated_at) ' +
        'values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )
    .bind(name, str(body.category), address || null, area || null, latitude, longitude, 'warga', email, now, now)
    .run();

  const created = await db
    .prepare('select * from locations where author_email = ? order by id desc limit 1')
    .bind(email)
    .first();

  if (!created) {
    return wantsJson(ctx)
      ? jsonResponse(503, { message: 'Tempat gagal disimpan.' })
      : redirectBack(ctx, { name: 'Tempat gagal disimpan.' });
  }

  return answer(ctx, created, false);
}
