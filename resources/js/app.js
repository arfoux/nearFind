import './vendor/liquidglass/components.js';

// Leaflet is only needed by the map page. Loading it on first paint of every
// other tab costs JS the visitor never uses, so the adapter is fetched on first
// map use. The bindings are assigned before the promise resolves, so every
// call site can use them directly once initMapPage() has awaited it.
let L;
let createLeafletMap;
let createTileLayer;
let setViewWithPadding;
let fitBoundsWithPadding;
let panTo;
let zoomTo;
let resizeMap;

let leafletPromise = null;

function ensureLeaflet() {
    if (!leafletPromise) {
        leafletPromise = import('./leaflet-adapter.js').then((adapter) => {
            L = adapter.L;
            createLeafletMap = adapter.createLeafletMap;
            createTileLayer = adapter.createTileLayer;
            setViewWithPadding = adapter.setViewWithPadding;
            fitBoundsWithPadding = adapter.fitBoundsWithPadding;
            panTo = adapter.panTo;
            zoomTo = adapter.zoomTo;
            resizeMap = adapter.resizeMap;
        });
    }
    return leafletPromise;
}

// Design read, kept next to the code it drives: a civic map tool for warga,
// utilitarian warm-neutral language, no brand hue. ENERGY 2 /
// RHYTHM 2 / MOTION 1 because a map utility must stay calm, low-motion and
// legible on a phone. The camera is the only thing that moves on purpose.
const NOMINATIM_SEARCH_ENDPOINT = 'https://nominatim.openstreetmap.org/search';
const NOMINATIM_RESULT_LIMIT = 5;
const PLACE_SEARCH_DEBOUNCE_MS = 350;
const PLACE_SEARCH_MIN_CHARS = 3;
const NOMINATIM_LOOKUP_ENDPOINT = 'https://nominatim.openstreetmap.org/lookup';
const AREA_DETAIL_CACHE_MS = 300000;
const AREA_GEOMETRY_MAX_POINTS = 6000;
const OSM_KIND_LABELS = {
    road: 'Jalan',
    village: 'Desa',
    city: 'Kota',
    district: 'Kabupaten',
    province: 'Provinsi',
    country: 'Negara',
    place: 'Tempat',
    area: 'Area',
};
const OSM_ROAD_TYPES = new Set([
    'road', 'street', 'residential', 'unclassified', 'living_street', 'pedestrian',
    'service', 'track', 'footway', 'path', 'cycleway', 'motorway', 'motorway_link',
    'trunk', 'trunk_link', 'primary', 'primary_link', 'secondary', 'secondary_link',
    'tertiary', 'tertiary_link', 'lane', 'avenue', 'alley', 'steps',
]);
const OSM_VILLAGE_TYPES = new Set(['village', 'hamlet', 'isolated_dwelling']);
const OSM_CITY_TYPES = new Set(['city', 'town']);
const OSM_DISTRICT_TYPES = new Set(['district', 'county', 'city_district', 'municipality', 'subregion']);
const OSM_PROVINCE_TYPES = new Set(['province', 'state', 'region']);
const OSM_AREA_TYPES = new Set(['suburb', 'neighbourhood', 'quarter', 'borough', 'administrative', 'boundary']);
const OSM_PLACE_DATASET_FIELDS = [
    'name', 'address', 'area', 'lat', 'lng', 'osmType', 'osmId', 'kindKey', 'kindLabel',
    'osmClass', 'osmTypeValue', 'addresstype', 'postcode', 'country', 'wikidata',
    'wikipedia', 'website', 'phone', 'openingHours', 'categoryId',
];
// Read-only OpenStreetMap POI overlay. Overpass and Nominatim are plain
// fetches; nothing here writes to the server. The panel only prefills the
// existing location sheet when a visitor explicitly saves a place.
const POI_DEFAULTS = {
    enabled: false,
    overpass_endpoint: 'https://overpass.private.coffee/api/interpreter',
    nominatim_reverse_endpoint: 'https://nominatim.openstreetmap.org/reverse',
    search_radius_m: 1000,
    max_results: 20,
    request_timeout_ms: 8000,
    cache_ms: 300000,
    tags: {
        amenity: ['place_of_worship', 'school', 'clinic', 'hospital', 'pharmacy', 'cafe', 'restaurant', 'bank', 'atm', 'post_office', 'library', 'police'],
        shop: ['supermarket', 'convenience', 'bakery'],
        leisure: ['park'],
        tourism: ['museum'],
    },
};
const OVERPASS_FALLBACK_ENDPOINT = 'https://overpass.nextgis.com/api/interpreter';
const POI_LANDMARK_TAGS = new Set([
    'hospital', 'museum', 'place_of_worship', 'school', 'park',
    'supermarket', 'police', 'library', 'bank',
]);
const POI_REFRESH_DEBOUNCE_MS = 350;
const POI_REVERSE_MIN_INTERVAL_MS = 1000;
const DECLUTTER_LIMITS = { 13: 10, 14: 16, 15: 26 };
const DECLUTTER_MIN_DISTANCE_PX = 26;
const DECLUTTER_MOVE_DEBOUNCE_MS = 200;

let poiConfig = { ...POI_DEFAULTS };
const poiMarkers = new Map();
const poiCache = new Map();
const reverseCache = new Map();
let currentPois = [];
let selectedPoiId = null;
let poiSeq = 0;
let poiTimer = null;
let poiController = null;
let poiReverseTimer = null;
let poiReverseController = null;
let lastPoiReverseAt = 0;

function normalizePoiTags(rawTags) {
    const entries = [];
    const add = (key, values) => {
        const safeKey = String(key ?? '').trim();

        if (!/^[a-zA-Z_][a-zA-Z0-9_:]*$/.test(safeKey)) {
            return;
        }

        const list = (Array.isArray(values) ? values : [values])
            .map((value) => String(value ?? '').trim())
            .filter((value) => value !== '' && value !== '*');
        entries.push({ key: safeKey, values: [...new Set(list)] });
    };

    if (rawTags && typeof rawTags === 'object' && !Array.isArray(rawTags)) {
        Object.entries(rawTags).forEach(([key, values]) => add(key, values));
    } else if (Array.isArray(rawTags)) {
        rawTags.forEach((tag) => {
            if (typeof tag === 'string') {
                const separator = tag.indexOf('=');
                add(separator > 0 ? tag.slice(0, separator) : tag, separator > 0 ? tag.slice(separator + 1).split('|') : []);
                return;
            }

            if (tag && typeof tag === 'object') {
                add(tag.key ?? tag.tag ?? tag.k, tag.values ?? tag.value ?? tag.v ?? []);
            }
        });
    }

    return entries.slice(0, 24);
}

function normalizePoiConfig(raw) {
    if (!raw || typeof raw !== 'object' || raw.enabled !== true) {
        return { ...POI_DEFAULTS, enabled: false, tags: normalizePoiTags(POI_DEFAULTS.tags) };
    }

    const numberOr = (value, fallback, min, max) => {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? Math.min(max, Math.max(min, parsed)) : fallback;
    };
    const endpoint = typeof raw.overpass_endpoint === 'string' && raw.overpass_endpoint.trim() !== ''
        ? raw.overpass_endpoint.trim()
        : POI_DEFAULTS.overpass_endpoint;
    const reverseEndpoint = typeof raw.nominatim_reverse_endpoint === 'string' && raw.nominatim_reverse_endpoint.trim() !== ''
        ? raw.nominatim_reverse_endpoint.trim()
        : POI_DEFAULTS.nominatim_reverse_endpoint;
    const tags = normalizePoiTags(raw.tags);

    return {
        enabled: true,
        overpass_endpoint: endpoint,
        nominatim_reverse_endpoint: reverseEndpoint,
        search_radius_m: Math.round(numberOr(raw.search_radius_m, POI_DEFAULTS.search_radius_m, 50, 3000)),
        max_results: Math.round(numberOr(raw.max_results, POI_DEFAULTS.max_results, 1, 100)),
        request_timeout_ms: numberOr(raw.request_timeout_ms, POI_DEFAULTS.request_timeout_ms, 1000, 30000),
        cache_ms: numberOr(raw.cache_ms, POI_DEFAULTS.cache_ms, 0, 3600000),
        tags: tags.length > 0 ? tags : normalizePoiTags(POI_DEFAULTS.tags),
    };
}

function escapePoiRegex(value) {
    return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function poiCacheKey(center) {
    const lat = Number(center?.lat);
    const lng = Number(center?.lng ?? center?.lon);

    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        return '';
    }

    return [
        poiConfig.overpass_endpoint,
        lat.toFixed(4),
        lng.toFixed(4),
        Math.round(poiConfig.search_radius_m),
        Math.round(poiConfig.max_results),
    ].join('|');
}

function buildOverpassQuery(center) {
    const lat = Number(center?.lat);
    const lng = Number(center?.lng ?? center?.lon);

    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        return '';
    }

    const around = `(around:${Math.round(poiConfig.search_radius_m)},${lat.toFixed(6)},${lng.toFixed(6)})`;
    const statements = poiConfig.tags.map(({ key, values }) => {
        const filter = values.length > 0 ? `~"^(${values.map(escapePoiRegex).join('|')})$"` : '';
        return `nwr["${key}"${filter}]${around};`;
    });

    if (statements.length === 0) {
        statements.push(`nwr${around};`);
    }

    const timeout = Math.max(5, Math.ceil(poiConfig.request_timeout_ms / 1000));
    return `[out:json][timeout:${timeout}];\n${statements.join('\n')}\nout center tags ${poiConfig.max_results};`;
}

const POI_CATEGORY_LABELS = {
    amenity: {
        cafe: 'Kafe',
        restaurant: 'Restoran',
        pharmacy: 'Apotek',
        clinic: 'Klinik',
        hospital: 'Rumah sakit',
        bank: 'Bank',
        atm: 'Anjungan tunai',
        post_office: 'Kantor pos',
        library: 'Perpustakaan',
        police: 'Kantor polisi',
        school: 'Sekolah',
        place_of_worship: 'Tempat ibadah',
    },
    shop: {
        supermarket: 'Supermarket',
        convenience: 'Toko kelontong',
        bakery: 'Toko roti',
    },
    leisure: { park: 'Taman' },
    tourism: { museum: 'Museum' },
};

function poiCategoryLabel(tags = {}) {
    for (const key of ['amenity', 'shop', 'leisure', 'tourism', 'healthcare', 'office']) {
        const value = String(tags[key] ?? '').trim();

        if (value === '') {
            continue;
        }

        if (POI_CATEGORY_LABELS[key]?.[value]) {
            return POI_CATEGORY_LABELS[key][value];
        }

        return 'Tempat';
    }

    return 'Tempat';
}

function poiCategoryId(tags = {}) {
    const amenity = String(tags.amenity ?? '');
    const shop = String(tags.shop ?? '');

    if (['cafe', 'restaurant'].includes(amenity) || shop === 'bakery') {
        return 'culinary';
    }

    if (['clinic', 'hospital', 'pharmacy'].includes(amenity) || String(tags.healthcare ?? '') !== '') {
        return 'health';
    }

    if (['supermarket', 'convenience'].includes(shop)) {
        return 'retail';
    }

    if (amenity === 'place_of_worship') {
        return 'worship';
    }

    if (amenity === 'school') {
        return 'education';
    }

    if (amenity === 'bank') {
        return 'bank';
    }

    if (amenity === 'atm') {
        return 'atm';
    }

    if (amenity === 'post_office') {
        return 'postal';
    }

    if (amenity === 'library') {
        return 'library';
    }

    if (amenity === 'police') {
        return 'police';
    }

    if (String(tags.leisure ?? '') === 'park') {
        return 'park';
    }

    if (String(tags.tourism ?? '') === 'museum') {
        return 'museum';
    }

    return '';
}

function isLandmarkPoi(poi) {
    const tags = poi?.tags ?? {};
    return ['amenity', 'shop', 'leisure', 'tourism']
        .some((key) => POI_LANDMARK_TAGS.has(String(tags[key] ?? '')));
}

function poiAddressFromTags(tags = {}) {
    const street = [tags['addr:housenumber'], tags['addr:street']].filter(Boolean).join(' ');
    const city = [tags['addr:suburb'], tags['addr:district'], tags['addr:city'], tags['addr:town'], tags['addr:village']]
        .filter(Boolean)
        .join(', ');

    return {
        address: [street, city].filter(Boolean).join(', '),
        postcode: String(tags['addr:postcode'] ?? '').trim(),
        area: String(tags['addr:suburb'] ?? tags['addr:district'] ?? tags['addr:city'] ?? tags['addr:town'] ?? tags['addr:village'] ?? '').trim(),
        phone: String(tags.phone ?? tags['contact:phone'] ?? '').trim(),
        website: String(tags.website ?? tags['contact:website'] ?? '').trim(),
        hours: String(tags.opening_hours ?? '').trim(),
    };
}

function normalizePoi(element) {
    const type = String(element?.type ?? '');

    if (!['node', 'way', 'relation'].includes(type)) {
        return null;
    }

    const rawId = element.id;

    if (!Number.isFinite(Number(rawId))) {
        return null;
    }

    let lat = Number(element.lat);
    let lng = Number(element.lon ?? element.lng);

    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        lat = Number(element.center?.lat);
        lng = Number(element.center?.lon ?? element.center?.lng);
    }

    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        return null;
    }

    const tags = element.tags && typeof element.tags === 'object' ? element.tags : {};
    const address = poiAddressFromTags(tags);

    return {
        id: `${type}/${rawId}`,
        name: String(tags.name ?? '').trim() || 'Tempat tanpa nama',
        category: poiCategoryLabel(tags),
        lat,
        lng,
        tags,
        source: 'osm',
        address: address.address,
        postcode: address.postcode,
        area: address.area,
        phone: address.phone,
        website: address.website,
        hours: address.hours,
    };
}

async function fetchOverpassOnce(endpoint, query, outerSignal) {
    const controller = new AbortController();
    const relayAbort = () => controller.abort();
    const timeoutId = window.setTimeout(() => {
        relayAbort();
    }, poiConfig.request_timeout_ms);

    if (outerSignal?.aborted) {
        relayAbort();
    } else {
        outerSignal?.addEventListener('abort', relayAbort, { once: true });
    }

    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            },
            body: new URLSearchParams({ data: query }).toString(),
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const payload = await response.json();

        if (!Array.isArray(payload?.elements)) {
            throw new Error('Format respons Overpass tidak dikenali.');
        }

        return {
            ok: true,
            pois: payload.elements
                .slice(0, poiConfig.max_results)
                .map((element) => normalizePoi(element))
                .filter(Boolean),
        };
    } catch (error) {
        return { ok: false, error };
    } finally {
        window.clearTimeout(timeoutId);
        outerSignal?.removeEventListener('abort', relayAbort);
    }
}

async function fetchOverpassPois(center, { signal } = {}) {
    const query = buildOverpassQuery(center);

    if (query === '') {
        return [];
    }

    const endpoints = [poiConfig.overpass_endpoint];
    if (endpoints[0] !== OVERPASS_FALLBACK_ENDPOINT) {
        endpoints.push(OVERPASS_FALLBACK_ENDPOINT);
    }

    let lastError = null;

    for (const endpoint of endpoints) {
        if (signal?.aborted) {
            return [];
        }

        const attempt = await fetchOverpassOnce(endpoint, query, signal);

        if (attempt.ok) {
            return attempt.pois;
        }

        lastError = attempt.error;

        if (signal?.aborted) {
            return [];
        }
    }

    if (lastError && lastError.name !== 'AbortError') {
        console.warn('nearFind: tempat dari OpenStreetMap tidak dapat dimuat.', lastError);
    }

    return [];
}

async function reverseGeocodePoi(poi, signal) {
    const controller = new AbortController();
    const relayAbort = () => controller.abort();
    const timeoutId = window.setTimeout(relayAbort, poiConfig.request_timeout_ms);

    signal?.addEventListener('abort', relayAbort, { once: true });

    if (signal?.aborted) {
        relayAbort();
    }

    try {
        const url = new URL(poiConfig.nominatim_reverse_endpoint);
        url.searchParams.set('format', 'jsonv2');
        url.searchParams.set('zoom', '16');
        url.searchParams.set('lat', String(poi.lat));
        url.searchParams.set('lon', String(poi.lng));

        const response = await fetch(url.toString(), {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const payload = await response.json();
        const address = payload?.address;

        if (!address || typeof address !== 'object') {
            return null;
        }

        const street = [address.house_number, address.road].filter(Boolean).join(' ');
        const locality = [address.suburb, address.city ?? address.town ?? address.village].filter(Boolean).join(', ');
        const detail = {
            address: [street, locality].filter(Boolean).join(', '),
            postcode: String(address.postcode ?? '').trim(),
            area: String(address.suburb ?? address.city ?? address.town ?? address.village ?? '').trim(),
        };

        return detail.address || detail.postcode || detail.area ? detail : null;
    } catch (error) {
        if (controller.signal.aborted) {
            return null;
        }

        console.warn('nearFind: alamat dari OpenStreetMap tidak dapat dimuat.', error);
        return null;
    } finally {
        window.clearTimeout(timeoutId);
        signal?.removeEventListener('abort', relayAbort);
    }
}

const THEME_STORAGE_KEY = 'nf-theme';
const THEME_DARK = 'dark';
const THEME_LIGHT = 'light';
const THEME_META_COLOR = { [THEME_DARK]: '#0B0E12', [THEME_LIGHT]: '#F7F5F1' };
// Both themes load the same keyless OSM raster tiles; a CSS filter on the
// tile pane supplies the dark basemap instead of a separate dark tile style.
const FALLBACK_DARK_STYLE = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const FALLBACK_LIGHT_STYLE = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

// Filled from the map page payload so a theme press uses the two styles the
// deployment actually advertises, not a hard-coded copy of them.
const themeStyles = { dark: FALLBACK_DARK_STYLE, light: FALLBACK_LIGHT_STYLE };

const FOCUSABLE_SELECTOR = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(', ');

const TOP_RESULT_HOOKS = {
    loading: '[data-place-top-loading]',
    error: '[data-place-top-error]',
    list: '[data-place-top-list]',
    empty: '[data-place-top-empty]',
};
const SHEET_RESULT_HOOKS = {
    loading: '[data-place-loading]',
    error: '[data-place-error]',
    list: '[data-place-list]',
    empty: '[data-place-empty]',
};

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    })[character]);
}

function icon(name, className = 'size-5') {
    return `<svg class="${className}" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#nf-${name}"></use></svg>`;
}

// This reads the same key and the same light default as the layout's pre-paint
// script. If the two ever disagree, the first paint flashes the wrong theme.
// R-31: first visit is light by design; a stored nf-theme choice stays authoritative.
function resolveTheme() {
    let stored = null;
    try {
        stored = window.localStorage.getItem(THEME_STORAGE_KEY);
    } catch {
        // Storage can be blocked; the light default still applies for this document.
    }

    return stored === THEME_DARK || stored === THEME_LIGHT ? stored : THEME_LIGHT;
}

function currentTheme() {
    return document.documentElement.dataset.theme === THEME_LIGHT ? THEME_LIGHT : THEME_DARK;
}

function applyTheme(theme, { broadcast = false } = {}) {
    document.documentElement.dataset.theme = theme;
    document.documentElement.style.colorScheme = theme;
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', THEME_META_COLOR[theme]);
    document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
        toggle.setAttribute('aria-pressed', theme === THEME_DARK ? 'true' : 'false');
    });

    if (broadcast) {
        document.dispatchEvent(new CustomEvent('nearfind:themechange', {
            detail: { theme, styleUrl: themeStyles[theme] },
        }));
    }
}

// Bound once at module load: the layout owns the toggle, so it survives every
// in-shell tab swap and must not be re-bound by initPage().
function initThemeToggle() {
    applyTheme(resolveTheme());

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-theme-toggle]')) {
            return;
        }

        const next = currentTheme() === THEME_DARK ? THEME_LIGHT : THEME_DARK;

        try {
            window.localStorage.setItem(THEME_STORAGE_KEY, next);
        } catch {
            // The mode still changes for this document when storage is blocked.
        }

        applyTheme(next, { broadcast: true });
    });
}

let liveAnnouncer = null;

function announce(message) {
    if (!message) {
        return;
    }

    if (!liveAnnouncer || !liveAnnouncer.isConnected) {
        liveAnnouncer = document.querySelector('[data-map-announcer]');

        if (!liveAnnouncer) {
            liveAnnouncer = document.createElement('div');
            liveAnnouncer.dataset.mapAnnouncer = '';
            liveAnnouncer.className = 'sr-only';
            liveAnnouncer.setAttribute('aria-live', 'polite');
            liveAnnouncer.setAttribute('aria-atomic', 'true');
            document.body.appendChild(liveAnnouncer);
        }
    }

    const region = liveAnnouncer;
    region.textContent = '';
    window.requestAnimationFrame(() => {
        if (region.isConnected) {
            region.textContent = message;
        }
    });
}

// One trap for every dialog. Each sheet keeps its own Enter/Escape behaviour;
// this only keeps Tab inside the open dialog and recovers focus that escaped.
function trapFocus(container, event) {
    if (event.key !== 'Tab' || !container) {
        return;
    }

    const focusable = [...container.querySelectorAll(FOCUSABLE_SELECTOR)]
        .filter((element) => element.getClientRects().length > 0 && !element.closest('[hidden]'));

    if (focusable.length === 0) {
        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    const outside = !container.contains(document.activeElement);

    if (event.shiftKey && (document.activeElement === first || outside)) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && (document.activeElement === last || outside)) {
        event.preventDefault();
        first.focus();
    }
}

function normalizeName(value) {
    return String(value ?? '')
        .toLocaleLowerCase('id-ID')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
}

function haversineMeters(lat1, lng1, lat2, lng2) {
    const radians = Math.PI / 180;
    const deltaLat = (lat2 - lat1) * radians;
    const deltaLng = (lng2 - lng1) * radians;
    const value = Math.sin(deltaLat / 2) ** 2
        + Math.cos(lat1 * radians) * Math.cos(lat2 * radians) * Math.sin(deltaLng / 2) ** 2;

    return 6371000 * 2 * Math.atan2(Math.sqrt(value), Math.sqrt(1 - value));
}

function areaFromAddress(address = {}) {
    return [
        address.village ?? address.suburb ?? address.neighbourhood ?? address.quarter,
        address.city_district ?? address.city ?? address.town ?? address.county,
    ].filter(Boolean).join(', ');
}

function parseBoundingBox(raw) {
    const parts = Array.isArray(raw) ? raw : String(raw ?? '').split(',');

    if (parts.length !== 4) {
        return null;
    }

    const values = parts.map((value) => Number(value));

    if (values.some((value) => !Number.isFinite(value))) {
        return null;
    }

    const [south, north, west, east] = values;

    if (south > north || west > east) {
        return null;
    }

    return values;
}

function classifyOsmKind(item = {}) {
    const category = String(item?.category ?? item?.class ?? '').trim().toLocaleLowerCase('id-ID');
    const type = String(item?.type ?? '').trim().toLocaleLowerCase('id-ID');
    const addressType = String(item?.addresstype ?? '').trim().toLocaleLowerCase('id-ID');
    const adminLevel = Number(item?.extratags?.admin_level ?? item?.admin_level ?? NaN);
    const placeRank = Number(item?.place_rank ?? NaN);

    if (addressType === 'country' || type === 'country') {
        return 'country';
    }

    if (OSM_PROVINCE_TYPES.has(addressType) || OSM_PROVINCE_TYPES.has(type) || adminLevel === 4) {
        return 'province';
    }

    if (OSM_DISTRICT_TYPES.has(addressType) || OSM_DISTRICT_TYPES.has(type)
        || (Number.isFinite(adminLevel) && adminLevel >= 5 && adminLevel <= 7)) {
        return 'district';
    }

    if (OSM_CITY_TYPES.has(addressType) || OSM_CITY_TYPES.has(type)) {
        return 'city';
    }

    if (OSM_VILLAGE_TYPES.has(addressType) || OSM_VILLAGE_TYPES.has(type)) {
        return 'village';
    }

    if (category === 'highway' || addressType === 'road' || OSM_ROAD_TYPES.has(type)) {
        return 'road';
    }

    if (category === 'boundary' || type === 'administrative' || OSM_AREA_TYPES.has(addressType)) {
        return 'area';
    }

    if (Number.isFinite(placeRank) && placeRank <= 12) {
        return 'area';
    }

    return 'place';
}

function normalizePlaceResult(item) {
    const lat = Number(item?.lat);
    const lng = Number(item?.lon);

    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        return null;
    }

    const address = item?.address ?? {};
    const extras = item?.extratags ?? {};
    const displayName = String(item?.display_name ?? '');
    const name = String(item?.name ?? item?.namedetails?.name ?? '').trim() || displayName.split(',')[0].trim();

    if (!name) {
        return null;
    }

    const kindKey = classifyOsmKind(item);

    const osmClass = String(item?.category ?? item?.class ?? '').trim();
    const osmTypeValue = String(item?.type ?? '').trim();
    const osmTags = { ...extras };

    if (osmClass !== '' && osmTypeValue !== '') {
        osmTags[osmClass] = osmTypeValue;
    }

    const poiLabel = poiCategoryLabel(osmTags);
    const kindLabel = kindKey === 'place' && poiLabel !== 'Tempat'
        ? poiLabel
        : (OSM_KIND_LABELS[kindKey] ?? OSM_KIND_LABELS.area);

    return {
        name,
        address: displayName,
        area: areaFromAddress(address),
        lat,
        lng,
        osmType: String(item?.osm_type ?? ''),
        osmId: String(item?.osm_id ?? ''),
        kindKey,
        kindLabel,
        categoryId: poiCategoryId(osmTags),
        osmClass,
        osmTypeValue,
        addresstype: String(item?.addresstype ?? ''),
        postcode: String(address.postcode ?? ''),
        country: String(address.country ?? ''),
        wikidata: String(extras.wikidata ?? ''),
        wikipedia: String(extras.wikipedia ?? ''),
        website: String(extras.website ?? address.website ?? ''),
        phone: String(extras.phone ?? address.phone ?? ''),
        openingHours: String(extras.opening_hours ?? '').trim(),
        boundingbox: parseBoundingBox(item?.boundingbox),
    };
}

function placeDataset(place = {}) {
    const dataset = {};

    OSM_PLACE_DATASET_FIELDS.forEach((field) => {
        dataset[field] = String(place[field] ?? '');
    });

    dataset.boundingbox = Array.isArray(place.boundingbox) ? place.boundingbox.join(',') : '';

    return dataset;
}

function placeFromDataset(dataset = {}) {
    const place = {};

    OSM_PLACE_DATASET_FIELDS.forEach((field) => {
        place[field] = String(dataset[field] ?? '');
    });

    place.lat = Number(dataset.lat);
    place.lng = Number(dataset.lng);
    place.boundingbox = parseBoundingBox(String(dataset.boundingbox ?? '').split(','));
    place.kindLabel = place.kindLabel || OSM_KIND_LABELS[place.kindKey] || OSM_KIND_LABELS.area;

    return place;
}

// A need without coordinates stays searchable and selectable. It has no
// camera target or marker, so every map use goes through this one guard.
function needPoint(need) {
    if (!need || need.latitude == null || need.longitude == null) {
        return null;
    }

    const lat = Number(need.latitude);
    const lng = Number(need.longitude);

    return Number.isFinite(lat) && Number.isFinite(lng) ? [lng, lat] : null;
}

// Payloads and hidden inputs keep [lng, lat]; Leaflet camera and marker calls
// take [lat, lng]. Every crossing goes through this one conversion.
function toLeafletLatLng([lng, lat]) {
    return [lat, lng];
}

async function fetchNominatimPlaces(query, { signal, viewbox } = {}) {
    const url = new URL(NOMINATIM_SEARCH_ENDPOINT);
    url.searchParams.set('format', 'jsonv2');
    url.searchParams.set('q', query);
    // Upstream returns up to 10; the top-search path applies the local limit of 5.
    url.searchParams.set('limit', '10');
    url.searchParams.set('addressdetails', '1');
    // Bias toward the current viewport without restricting results to it.
    if (Array.isArray(viewbox) && viewbox.length === 4) {
        url.searchParams.set('viewbox', viewbox.join(','));
    }

    const response = await fetch(url, { signal, headers: { Accept: 'application/json' } });

    if (!response.ok) {
        throw new Error(`Nominatim HTTP ${response.status}`);
    }

    const payload = await response.json();

    if (!Array.isArray(payload)) {
        return [];
    }

    return payload.map(normalizePlaceResult).filter(Boolean);
}

const osmDetailCache = new Map();

function normalizedOsmType(value) {
    const raw = String(value ?? '').trim().toLocaleLowerCase('id-ID');

    if (['n', 'node'].includes(raw)) {
        return 'N';
    }

    if (['w', 'way'].includes(raw)) {
        return 'W';
    }

    if (['r', 'relation'].includes(raw)) {
        return 'R';
    }

    return '';
}

function osmDetailKey(place) {
    const type = normalizedOsmType(place?.osmType);
    const id = String(place?.osmId ?? '').trim();

    return type && id ? `${type}${id}` : '';
}

function cachedOsmDetail(key) {
    const entry = osmDetailCache.get(key);

    if (!entry) {
        return null;
    }

    if (Date.now() - entry.at > AREA_DETAIL_CACHE_MS) {
        osmDetailCache.delete(key);
        return null;
    }

    return entry.detail;
}

function normalizeOsmDetail(item, place) {
    const detailPlace = normalizePlaceResult(item);
    const address = item?.address ?? {};
    const extras = item?.extratags ?? {};

    return {
        ...place,
        address: detailPlace?.address || place.address,
        area: detailPlace?.area || place.area,
        kindLabel: detailPlace?.kindLabel && detailPlace.kindLabel !== OSM_KIND_LABELS.place
            ? detailPlace.kindLabel
            : (place.kindLabel || detailPlace?.kindLabel || OSM_KIND_LABELS.area),
        categoryId: detailPlace?.categoryId || place.categoryId || '',
        postcode: String(address.postcode ?? place.postcode ?? ''),
        country: String(address.country ?? place.country ?? ''),
        wikidata: String(extras.wikidata ?? place.wikidata ?? ''),
        wikipedia: String(extras.wikipedia ?? place.wikipedia ?? ''),
        website: String(extras.website ?? address.website ?? place.website ?? ''),
        phone: String(extras.phone ?? address.phone ?? place.phone ?? ''),
        openingHours: String(extras.opening_hours ?? place.openingHours ?? '').trim(),
        geojson: item?.geojson ?? null,
        boundingbox: parseBoundingBox(item?.boundingbox ?? place.boundingbox),
    };
}

async function fetchOsmPlaceDetail(place, { signal } = {}) {
    const key = osmDetailKey(place);

    if (!key) {
        return null;
    }

    const cached = cachedOsmDetail(key);

    if (cached) {
        return cached;
    }

    const url = new URL(NOMINATIM_LOOKUP_ENDPOINT);
    url.searchParams.set('osm_ids', key);
    url.searchParams.set('format', 'jsonv2');
    url.searchParams.set('addressdetails', '1');
    url.searchParams.set('extratags', '1');
    url.searchParams.set('namedetails', '1');
    url.searchParams.set('polygon_geojson', '1');

    const response = await fetch(url, { signal, headers: { Accept: 'application/json' } });

    if (!response.ok) {
        throw new Error(`Nominatim lookup HTTP ${response.status}`);
    }

    const payload = await response.json();
    const item = Array.isArray(payload) ? payload[0] : null;

    if (!item) {
        return null;
    }

    const detail = normalizeOsmDetail(item, place);
    osmDetailCache.set(key, { at: Date.now(), detail });

    return detail;
}

function countCoordinatePairs(value, depth = 0) {
    if (depth > 8 || value == null) {
        return 0;
    }

    if (Array.isArray(value)) {
        if (value.length === 2 && value.every((entry) => Number.isFinite(entry))) {
            return 1;
        }

        return value.reduce((total, entry) => total + countCoordinatePairs(entry, depth + 1), 0);
    }

    if (typeof value === 'object') {
        if (Array.isArray(value.coordinates)) {
            return countCoordinatePairs(value.coordinates, depth + 1);
        }

        if (Array.isArray(value.geometries)) {
            return countCoordinatePairs(value.geometries, depth + 1);
        }

        if (value.geometry) {
            return countCoordinatePairs(value.geometry, depth + 1);
        }

        if (Array.isArray(value.features)) {
            return countCoordinatePairs(value.features, depth + 1);
        }
    }

    return 0;
}

function osmObjectPath(place) {
    const type = normalizedOsmType(place?.osmType);
    const id = String(place?.osmId ?? '').trim();

    if (!type || !id) {
        return '';
    }

    const names = { N: 'node', W: 'way', R: 'relation' };

    return `${names[type]}/${encodeURIComponent(id)}`;
}

function setChildHidden(root, selector, hidden) {
    const child = root?.querySelector(selector);

    if (!child) {
        return;
    }

    if (hidden) {
        child.setAttribute('hidden', '');
    } else {
        child.removeAttribute('hidden');
    }
}

function resetPlaceResults(root) {
    if (!root) {
        return;
    }

    root.setAttribute('hidden', '');
    [...Object.values(TOP_RESULT_HOOKS), ...Object.values(SHEET_RESULT_HOOKS)].forEach((selector) => {
        setChildHidden(root, selector, true);
    });

    const list = root.querySelector(TOP_RESULT_HOOKS.list) ?? root.querySelector(SHEET_RESULT_HOOKS.list);

    if (list) {
        list.textContent = '';
    }
}

function renderPlaceResults(root, { status, results = [], hooks }) {
    if (!root) {
        return;
    }

    const list = root.querySelector(hooks.list);
    root.removeAttribute('hidden');
    setChildHidden(root, hooks.loading, status !== 'loading');
    setChildHidden(root, hooks.error, status !== 'error');
    setChildHidden(root, hooks.empty, status !== 'ready' || results.length !== 0);
    setChildHidden(root, hooks.list, status !== 'ready' || results.length === 0);

    if (status === 'loading') {
        const loading = root.querySelector(hooks.loading);
        if (loading) loading.textContent = 'Mencari tempat…';
    } else if (status === 'error') {
        const error = root.querySelector(hooks.error);
        if (error) error.textContent = 'Pencarian tempat gagal. Periksa koneksimu.';
    } else if (status === 'ready' && results.length === 0) {
        const empty = root.querySelector(hooks.empty);
        if (empty) empty.textContent = 'Tempat tidak ditemukan. Coba kata kunci lain.';
    }

    if (status !== 'ready' || !list) {
        return;
    }

    list.textContent = '';

    results.forEach((place) => {
        const item = document.createElement('li');
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.placeResult = '';
        Object.entries(placeDataset(place)).forEach(([key, value]) => {
            button.dataset[key] = value;
        });
        button.className = 'flex min-h-[44px] w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40';
        button.setAttribute('aria-label', `Pilih ${place.name}`);
        button.innerHTML = `
            <span class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-200">${icon('pin', 'size-4')}</span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-[12px] font-semibold text-ink">${escapeHtml(place.name)}</span>
                <span class="block truncate text-[10.5px] leading-snug text-muted">${escapeHtml([place.kindLabel, place.address].filter(Boolean).join(' · '))}</span>
            </span>
        `;
        item.append(button);
        list.append(item);
    });
}

function bindPlaceResults(root, handler) {
    if (!root) {
        return;
    }

    root.addEventListener('click', (event) => {
        const button = event.target.closest('[data-place-result]');

        if (!button || !root.contains(button)) {
            return;
        }

        handler(placeFromDataset(button.dataset));
    });
}

// The pin stays at the map centre: nudge, zoom and locate move the camera
// underneath it. One factory serves two scoped instances so a press in the
// need sheet can never touch the location sheet's hidden inputs.
function createPointPicker({
    root,
    picker,
    canvas,
    hint,
    summary,
    busy,
    latInput,
    lngInput,
    areaInput,
    stepAttr,
    zoomAttr,
    locateAttr,
    initialPoint,
    styleUrl,
    summarySuffix,
    signal,
    notify,
}) {
    let map = null;
    let syncing = false;
    let areaTimer = null;
    let areaRequest = 0;
    let disposed = false;
    let tileLayer = null;
    let tileUrl = '';

    function putPoint(lat, lng) {
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return;
        }

        if (latInput) latInput.value = lat.toFixed(6);
        if (lngInput) lngInput.value = lng.toFixed(6);
        hint?.classList.add('hidden');

        if (summary) {
            summary.textContent = `${lat.toFixed(5)}, ${lng.toFixed(5)} · ${summarySuffix}`;
        }
    }

    // Real reverse geocoding, bounded and debounced: a nudged pin must not fire
    // a request per pixel, and a failed lookup leaves the field editable.
    function fetchArea(lat, lng) {
        if (!areaInput || areaInput.value.trim() !== '') {
            return;
        }

        window.clearTimeout(areaTimer);
        areaTimer = window.setTimeout(async () => {
            if (disposed || signal?.aborted) {
                return;
            }

            const request = ++areaRequest;
            busy?.classList.remove('hidden');
            busy?.classList.add('flex');

            try {
                const response = await fetch(
                    `https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=16&lat=${lat}&lon=${lng}`,
                    { headers: { Accept: 'application/json' }, signal },
                );

                if (!response.ok || request !== areaRequest || disposed) {
                    return;
                }

                const data = await response.json();

                if (areaInput && areaInput.value.trim() === '') {
                    const area = areaFromAddress(data?.address ?? {});
                    if (area) areaInput.value = area;
                }
            } catch {
                // Offline or rate limited: the visitor can still type the area.
            } finally {
                if (request === areaRequest) {
                    busy?.classList.add('hidden');
                    busy?.classList.remove('flex');
                }
            }
        }, 420);
    }

    function centerOn(lat, lng, { zoom } = {}) {
        if (!map || !Number.isFinite(lat) || !Number.isFinite(lng)) {
            return;
        }

        syncing = true;

        try {
            setViewWithPadding(map, [lat, lng], zoom);
        } finally {
            syncing = false;
        }

        putPoint(lat, lng);
        fetchArea(lat, lng);
    }

    function commit() {
        if (!map || syncing) {
            return;
        }

        const center = map.getCenter();
        putPoint(center.lat, center.lng);
        fetchArea(center.lat, center.lng);
    }

    // A deterministic camera move fires moveend even when animation frames are
    // throttled, so the point is committed without waiting for a transition.
    function nudge(direction) {
        if (!map) {
            return;
        }

        const step = direction === 'west' ? [-0.0015, 0]
            : direction === 'east' ? [0.0015, 0]
                : direction === 'north' ? [0, 0.0015] : [0, -0.0015];
        const center = map.getCenter();
        setViewWithPadding(map, [center.lat + step[1], center.lng + step[0]], map.getZoom());
    }

    function zoomBy(delta) {
        if (map) {
            zoomTo(map, map.getZoom() + delta, 0);
        }
    }

    function locate() {
        if (!('geolocation' in navigator)) {
            notify('Browser ini tidak mendukung fitur lokasi.');
            return;
        }

        navigator.geolocation.getCurrentPosition(
            ({ coords }) => {
                if (disposed || !map) {
                    return;
                }

                if (areaInput) areaInput.value = '';
                centerOn(coords.latitude, coords.longitude, { zoom: 17 });
            },
            () => notify('Lokasi kamu tidak bisa dibaca. Geser peta saja.'),
            { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 },
        );
    }

    // Returns true only when a new map was created. Callers use that to decide
    // whether they still need to recentre on a freshly prefilled place.
    function ensure() {
        if (!picker || !canvas || map || disposed) {
            return false;
        }

        const start = initialPoint();

        try {
            map = createLeafletMap(canvas, {
                center: toLeafletLatLng(start),
                zoom: 16,
                minZoom: 12,
                maxZoom: 18,
            });
            tileUrl = styleUrl();
            tileLayer = createTileLayer(tileUrl);
            tileLayer.addTo(map);
            resizeMap(map);
        } catch (error) {
            console.error('nearFind: pemilih lokasi gagal dimuat.', error);
            map = null;
            return false;
        }

        map.on('moveend', commit);
        map.on('click', commit);
        centerOn(start[1], start[0]);

        return true;
    }

    const attributeSelector = (key) =>
        `[data-${key.replace(/[A-Z]/g, (character) => `-${character.toLowerCase()}`)}]`;

    function bind() {
        root?.querySelectorAll(attributeSelector(stepAttr)).forEach((button) => {
            button.addEventListener('click', () => nudge(button.dataset[stepAttr]));
        });

        root?.querySelectorAll(attributeSelector(zoomAttr)).forEach((button) => {
            button.addEventListener('click', () => zoomBy(button.dataset[zoomAttr] === 'in' ? 1 : -1));
        });

        root?.querySelector(attributeSelector(locateAttr))?.addEventListener('click', locate);
    }

    function setStyle(url) {
        try {
            if (map && tileLayer && url && url !== tileUrl) {
                tileUrl = url;
                tileLayer.setUrl(url);
            }
        } catch {
            // A style swap during a camera transition is harmless.
        }
    }

    function destroy() {
        disposed = true;
        window.clearTimeout(areaTimer);

        if (map) {
            try {
                map.remove();
            } catch {
                // Already detached by a tab swap.
            }

            map = null;
        }
    }

    return { ensure, bind, centerOn, setStyle, destroy };
}

const DEFAULT_MAP_STYLE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

// The four tabs are separate endpoints, but they share one shell. Navigating
// between them is a fetch + swap (with a skeleton in between) instead of a fresh
// document, so the map/list state only changes where the page actually re-inits.
const SPA_PATHS = new Set(['/', '/explore', '/reports', '/profile']);

// A tab may redirect out of the shell: a guest pressing Profil lands on /auth,
// which is a standalone document (no app shell). Those can never be swapped in
// place, so the first hop goes through a full page load.
const SHELL_LESS_PATHS = new Set(['/auth']);

let disposeCurrentPage = null;
let mapInitGeneration = 0;
let isNavigating = false;

initThemeToggle();
initSpaNavigation();
initPage();

// The server renders every tab's skeleton only so the first paint is a shape and
// not a blank column. The page it was painting under is ready by the time this
// module runs, so the layer goes away for good: it sits above the content and a
// rectangle of the same size, which is exactly why a finished page still looked
// like it was loading.
function hideServerSkeletons() {
    document.querySelectorAll('[data-skeleton-slot]').forEach((slot) => {
        slot.setAttribute('hidden', '');
    });
}

hideServerSkeletons();

function initPage() {
    initPasswordToggles();
    initAuthTabs();
    initNeedsList();
    initMapPage();
}

async function initMapPage() {
    const dataElement = document.getElementById('nearfind-data');
    const needCardElement = document.getElementById('need-card');
    const mapElement = document.getElementById('map');

    if (!dataElement || !needCardElement || !mapElement) {
        return;
    }
    const generation = ++mapInitGeneration;

    try {
        await ensureLeaflet();
        if (generation !== mapInitGeneration) {
            return;
        }
        disposeCurrentPage = initNearFind(JSON.parse(dataElement.textContent), {
            mapElement,
            needCardElement,
        });
    } catch (error) {
        console.error('nearFind: gagal menginisialisasi halaman.', error);
    }
}

function disposePage() {
    if (typeof disposeCurrentPage === 'function') {
        try {
            disposeCurrentPage();
        } catch (error) {
            console.error('nearFind: gagal membersihkan halaman.', error);
        }
    }

    mapInitGeneration++;
    disposeCurrentPage = null;
}

function initSpaNavigation() {
    document.addEventListener('click', (event) => {
        if (
            event.defaultPrevented ||
            event.button !== 0 ||
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            event.altKey
        ) {
            return;
        }

        const link = event.target.closest('[data-nav-link], [data-app-link]');

        if (!link || link.target === '_blank' || link.hasAttribute('download')) {
            return;
        }

        const url = new URL(link.href, window.location.href);

        if (url.origin !== window.location.origin || !isSpaPath(url.pathname)) {
            return;
        }

        if (url.pathname === window.location.pathname && url.search === window.location.search) {
            event.preventDefault();
            return;
        }

        event.preventDefault();
        navigateTo(url.href);
    });

    window.addEventListener('popstate', () => {
        if (isSpaPath(window.location.pathname)) {
            navigateTo(window.location.href, { pushState: false });
        }
    });
}

function isSpaPath(pathname) {
    return SPA_PATHS.has(pathname.length > 1 ? pathname.replace(/\/+$/, '') : pathname);
}

async function navigateTo(url, { pushState = true } = {}) {
    if (isNavigating) {
        return;
    }

    const shell = document.querySelector('[data-app-shell]');
    const content = document.querySelector('[data-page-content]');

    if (!shell || !content) {
        window.location.href = url;

        return;
    }

    isNavigating = true;

    // The tab the press is FOR, resolved from the URL being navigated to. It used
    // to be read off window.location before pushState ran, so the nav lit up for
    // the page being left and only corrected itself once the response arrived.
    let targetTab = tabForPathname(new URL(url, window.location.href).pathname);

    if (pushState) {
        window.history.pushState({ nearfind: true }, '', url);
    }

    shell.dataset.tab = targetTab;

    // The nav answers the press immediately, in the same frame as the skeleton:
    // waiting for the response made the button feel dead for a network round trip.
    syncNav(targetTab);

    document.querySelectorAll('[data-nav-link]').forEach((link) => {
        link.dataset.pending = link.dataset.navKey === targetTab ? 'true' : 'false';
    });

    // The tab's own skeleton paints first (the exact shape it had on the first
    // visit), then the fetched page swaps in underneath it and fades in. The
    // skeleton can be very fast, so give the paint a frame to land.
    const skeleton = buildSkeleton(targetTab);

    // Dispose BEFORE the old markup goes: Leaflet's remove() needs the container
    // it was given, and emptying the slot first detaches it mid-teardown.
    disposePage();

    content.innerHTML = '';
    content.setAttribute('aria-busy', 'true');

    skeleton?.prependFor(content);

    // One frame, so the skeleton is painted before the network call can finish.
    await new Promise((resolve) => requestAnimationFrame(() => resolve()));

    try {
        const response = await fetch(url, {
            cache: 'no-store',
            credentials: 'same-origin',
            redirect: 'follow',
            headers: { Accept: 'text/html' },
        });

        // The server may have redirected to a page outside the shell (/auth for
        // a guest). There is nothing to swap: load that document properly.
        if (response.redirected && SHELL_LESS_PATHS.has(new URL(response.url).pathname)) {
            window.location.href = response.url;

            return;
        }

        // The server may have redirected (a signed-in visitor pressing Profil
        // lands back on the page they can see). Keep the address bar truthful.
        if (response.redirected && response.url) {
            window.history.replaceState({ nearfind: true }, '', response.url);
        }

        const landedPath = response.redirected ? new URL(response.url).pathname : new URL(url).pathname;

        targetTab = tabForPathname(landedPath);
        shell.dataset.tab = targetTab;

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
        const nextShell = parsed.querySelector('[data-app-shell]');
        const nextContent = parsed.querySelector('[data-page-content]');
        const nextToast = parsed.querySelector('#toast');

        if (!nextShell || !nextContent) {
            throw new Error('Halaman tujuan tidak memiliki kerangka aplikasi.');
        }

        // The fresh body class always wins: a skeleton injected on the old shell
        // must never leak the previous page's background.
        document.body.className = parsed.body.className;
        shell.className = nextShell.className;
        shell.dataset.tab = targetTab;

        // The skeleton layer is the WAIT, never the page. Once the real content is
        // in the slot, nothing of the server-rendered layer may stay painted: it
        // is a full-size, absolutely positioned copy of the same page, so leaving
        // it visible made every loaded tab look like it was still loading.
        document.querySelectorAll('[data-skeleton-slot]').forEach((slot) => {
            slot.setAttribute('hidden', '');
        });

        // The skeleton belonged to the WAIT, not to the page. It is removed the
        // moment the real content is in place: keeping it on top (and fading the
        // page in under it) is what made a loaded page still look like a
        // skeleton.
        content.innerHTML = nextContent.innerHTML;

        skeleton?.remove();
        content.removeAttribute('aria-busy');
        content.style.opacity = '';
        content.style.transition = '';

        syncNav(targetTab);

        document.querySelectorAll('[data-nav-link]').forEach((link) => {
            delete link.dataset.pending;
        });

        if (nextToast) {
            document.querySelector('#toast')?.setAttribute('class', nextToast.className);
        }

        document.title = parsed.title;
        initPage();
    } catch (error) {
        console.error('nearFind: navigasi gagal, memuat ulang halaman.', error);
        window.location.href = url;

        return;
    } finally {
        isNavigating = false;
    }
}

function tabForPathname(pathname) {
    const path = pathname.length > 1 ? pathname.replace(/\/+$/, '') : pathname;

    switch (path) {
        case '/explore':
            return 'jelajahi';
        case '/reports':
            return 'laporan';
        case '/profile':
            return 'profil';
        default:
            return 'peta';
    }
}

// The nav is rendered once by the server, so the current tab has to be
// repainted on every shell swap. `<lg-navbar>` owns the sliding indicator:
// setCurrent() moves it and mirrors aria-current, so no class swapping here.
function syncNav(tab) {
    const navbar = document.querySelector('lg-navbar');

    document.querySelectorAll('[data-nav-link]').forEach((link, index) => {
        const isActive = link.dataset.navKey === tab;

        if (isActive) {
            link.setAttribute('aria-current', 'page');
        } else {
            link.removeAttribute('aria-current');
        }

        if (isActive && navbar && typeof navbar.setCurrent === 'function') {
            navbar.setCurrent(index);
        }
    });
}

// The skeleton a tab shows while its page is in flight is the one the server
// already rendered for that tab (partials/skeleton.blade.php), same markup, so
// the shapes a visitor sees on a press match the shapes of the page that lands.
// Only the frame differs: the server paints it underneath the page, a press
// needs it above the (now empty) page slot until the swap.
function buildSkeleton(tab) {
    const template = document.querySelector(`[data-page-skeleton] [data-skeleton-slot="${tab}"] [data-skeleton-pane]`);

    if (!template) {
        return null;
    }

    return {
        prependFor(content) {
            const pane = template.cloneNode(true);
            const frame = document.createElement('div');
            const isMap = pane.dataset.skeletonPane === 'peta';

            frame.className = isMap
                ? 'pointer-events-none absolute inset-0 z-[300] bg-canvas'
                : 'pointer-events-none absolute inset-0 z-[300] flex flex-col bg-canvas';

            frame.dataset.pendingSkeleton = 'true';
            frame.setAttribute('aria-hidden', 'true');
            frame.append(pane);

            // The page slot is emptied before the skeleton goes in, so it has to
            // be positioned against the shell, not against a zero-height child.
            if (getComputedStyle(content).position === 'static') {
                content.style.position = 'relative';
            }

            content.append(frame);
        },
        // The skeleton is a placeholder for the wait and nothing else: the swap
        // removes it synchronously, so a page that has its content never keeps a
        // shimmering copy of itself on top.
        remove() {
            document.querySelector('[data-pending-skeleton]')?.remove();
        },
    };
}

function initPasswordToggles() {
    document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const input = toggle.parentElement?.querySelector('[data-password-input]');

            if (!input) {
                return;
            }

            const reveal = input.type === 'password';

            input.type = reveal ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', reveal ? 'true' : 'false');
            toggle.setAttribute('aria-label', reveal ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
            toggle.querySelector('use')?.setAttribute('href', reveal ? '#nf-eye-off' : '#nf-eye');
        });
    });
}

// The auth page renders both forms and switches between them client-side. The
// register form is reachable by URL too (`/auth?mode=register`), which is what a
// failed registration redirects back to.
function initAuthTabs() {
    const root = document.querySelector('[data-auth]');

    if (!root) {
        return;
    }

    const tabs = [...root.querySelectorAll('[data-auth-tab]')];
    const panels = [...root.querySelectorAll('[data-auth-panel]')];
    const params = new URLSearchParams(window.location.search);
    const requestedMode = params.get('mode');
    const startsAsRegister =
        requestedMode === 'register' ||
        window.location.hash === '#register' ||
        root.dataset.authInitial === 'register';
    let mode = startsAsRegister ? 'register' : 'login';

    const applyMode = ({ updateUrl = false, focusPanel = false } = {}) => {
        tabs.forEach((tab) => {
            const isActive = tab.dataset.authTab === mode;

            if (tab.getAttribute('role') === 'tab') {
                tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                tab.tabIndex = isActive ? 0 : -1;
            }
        });

        panels.forEach((panel) => {
            const isActive = panel.dataset.authPanel === mode;
            panel.hidden = !isActive;

            if (isActive && focusPanel) {
                panel.querySelector('input:not([type="hidden"]), button, a')?.focus({ preventScroll: true });
            }
        });

        if (updateUrl) {
            const url = new URL(window.location.href);

            if (mode === 'register') {
                url.searchParams.set('mode', 'register');
            } else {
                url.searchParams.delete('mode');
            }

            url.hash = '';
            window.history.replaceState({}, '', url);
        }
    };

    tabs.forEach((tab) => {
        tab.addEventListener('click', (event) => {
            event.preventDefault();
            mode = tab.dataset.authTab === 'register' ? 'register' : 'login';
            applyMode({ updateUrl: true, focusPanel: tab.getAttribute('role') !== 'tab' });
        });
    });

    root.querySelector('[role="tablist"]')?.addEventListener('keydown', (event) => {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
            return;
        }

        event.preventDefault();
        mode = mode === 'login' ? 'register' : 'login';
        applyMode({ updateUrl: true });

        tabs.find((tab) => tab.dataset.authTab === mode && tab.getAttribute('role') === 'tab')?.focus();
    });

    applyMode();
}

// Every word must appear somewhere in the haystack, in any order: "sukajadi
// laundry" finds the laundry in Sukajadi, which a single substring test missed.
function matchesWords(haystack, query) {
    const words = String(query || '')
        .toLocaleLowerCase('id-ID')
        .split(/\s+/)
        .filter(Boolean);

    if (words.length === 0) {
        return true;
    }

    const hay = String(haystack || '').toLocaleLowerCase('id-ID');

    return words.every((word) => hay.includes(word));
}

// The Jelajahi page renders every need on the server; the search box and the
// category chips filter that list in place.
function initNeedsList() {
    const list = document.querySelector('[data-needs-list]');

    if (!list) {
        return;
    }

    const searchInput = document.querySelector('[data-list-search]');
    const clearButton = document.querySelector('[data-list-clear]');
    const emptyState = document.querySelector('[data-list-empty]');
    const chips = [...document.querySelectorAll('[data-list-category]')];
    const rows = [...list.querySelectorAll('[data-need-row]')];
    const state = { category: 'all', query: '' };

    const applyFilters = () => {
        let visible = 0;

        rows.forEach((row) => {
            const matchesCategory = state.category === 'all' || row.dataset.category === state.category;
            const matchesQuery = matchesWords(row.dataset.search, state.query);
            const show = matchesCategory && matchesQuery;

            row.hidden = !show;
            visible += show ? 1 : 0;
        });

        if (emptyState) {
            emptyState.hidden = visible > 0;
        }

        if (clearButton) {
            clearButton.hidden = state.query === '';
        }
    };

    chips.forEach((chip) => {
        chip.addEventListener('click', () => {
            state.category = chip.dataset.listCategory ?? 'all';

            chips.forEach((candidate) => {
                const isActive = candidate === chip;

                candidate.classList.toggle('is-active', isActive);
                candidate.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });

            applyFilters();
        });
    });

    searchInput?.addEventListener('input', () => {
        state.query = searchInput.value.trim().toLowerCase();
        applyFilters();
    });

    clearButton?.addEventListener('click', () => {
        searchInput.value = '';
        state.query = '';
        searchInput.focus();
        applyFilters();
    });

    applyFilters();
}

function initNearFind(
    { categories, needs, locations = [], authenticated = false, mapStyleUrl = DEFAULT_MAP_STYLE_URL, mapDarkStyleUrl, mapLightStyleUrl, flash = null, poi = null },
    { mapElement, needCardElement },
) {
    // Tile URLs are shared across themes; dark mode is a CSS filter on the
    // tile pane, so both keys resolve to a raster template either way.
    themeStyles.dark = mapDarkStyleUrl || mapStyleUrl || FALLBACK_DARK_STYLE;
    themeStyles.light = mapLightStyleUrl || mapStyleUrl || FALLBACK_LIGHT_STYLE;
    poiConfig = normalizePoiConfig(poi);

    const categoryById = Object.fromEntries(categories.map((category) => [category.id, category]));
    const fallbackCategory = categoryById.all ?? categories[0];
    const needsList = Array.isArray(needs) ? needs : [];
    const mappableNeeds = needsList.filter((need) => needPoint(need) !== null);
    const mappableLocations = (Array.isArray(locations) ? locations : []).filter(
        (location) => Number.isFinite(Number(location.latitude)) && Number.isFinite(Number(location.longitude)),
    );

    if (mappableNeeds.length !== needsList.length) {
        console.warn('nearFind: beberapa kebutuhan belum memiliki titik peta dan tidak diberi penanda.', needsList.length - mappableNeeds.length);
    }

    const state = {
        categories,
        needs: needsList,
        locations: mappableLocations,
        category: 'all',
        query: '',
        sort: 'nearest',
        selectedId: null,
        selectedLocationId: null,
        filtered: [],
        markers: new Map(),
        locationMarkers: new Map(),
        authenticated: Boolean(authenticated),
    };

    const searchInput = document.getElementById('need-search');
    const searchForm = document.querySelector('[data-search-form]');
    const searchClear = document.querySelector('[data-search-clear]');
    const placeTopResults = document.querySelector('[data-place-top-results]');
    const filterDot = document.querySelector('[data-filter-dot]');
    const filterSheet = document.getElementById('filter-sheet');
    const filterForm = document.getElementById('filter-form');
    const filterOpenButton = document.querySelector('[data-action="open-filter"]');
    const loginGate = document.getElementById('login-gate');
    const loginMessage = loginGate?.querySelector('[data-login-message]');
    const loginSubmit = loginGate?.querySelector('[data-login-submit]');
    const submitSheet = document.getElementById('submit-sheet');
    const locationSheet = document.getElementById('location-sheet');
    const locationForm = locationSheet?.querySelector('[data-location-form]');
    const locationPlaceSearch = locationSheet?.querySelector('[data-place-search]');
    const locationResults = locationSheet?.querySelector('[data-place-results]');
    const locationNameInput = locationSheet?.querySelector('[data-location-name]');
    const locationAddressInput = locationSheet?.querySelector('[data-location-address]');
    const locationAreaInput = locationSheet?.querySelector('[data-location-area]');
    const locationLatInput = locationSheet?.querySelector('[data-location-lat]');
    const locationLngInput = locationSheet?.querySelector('[data-location-lng]');
    const locationCategoryInput = locationSheet?.querySelector('[data-location-category]');
    const locationPanel = document.querySelector('[data-location-panel]');
    const locationPanelTitle = locationPanel?.querySelector('[data-location-panel-title]');
    const locationPanelMeta = locationPanel?.querySelector('[data-location-panel-meta]');
    const locationPanelClose = locationPanel?.querySelector('[data-location-panel-close]');
    const poiPanel = document.querySelector('[data-poi-panel]');
    const poiPanelTitle = poiPanel?.querySelector('[data-poi-panel-title]');
    const poiPanelMeta = poiPanel?.querySelector('[data-poi-panel-meta]');
    const poiPanelBody = poiPanel?.querySelector('[data-poi-panel-body]');
    const poiPanelClose = poiPanel?.querySelector('[data-poi-panel-close]');
    const poiRouteLink = poiPanel?.querySelector('[data-poi-route]');
    const areaPanel = document.querySelector('[data-area-panel]');
    const areaPanelTitle = areaPanel?.querySelector('[data-area-panel-title]');
    const areaPanelMeta = areaPanel?.querySelector('[data-area-panel-meta]');
    const areaPanelKicker = areaPanel?.querySelector('[data-area-panel-kicker]');
    const areaPanelBody = areaPanel?.querySelector('[data-area-panel-body]');
    const areaPanelClose = areaPanel?.querySelector('[data-area-panel-close]');
    const areaRouteLink = areaPanel?.querySelector('[data-area-route]');
    const toast = document.getElementById('toast');
    const toastMessage = toast?.querySelector('[data-toast-message]');
    const mapTip = document.querySelector('[data-map-tip]');
    const needPanel = document.getElementById('need-panel');
    const needPeekElement = document.getElementById('need-peek');
    const mapControls = document.querySelector('[data-map-controls]');
    const mapLoading = document.querySelector('[data-map-loading]');
    const desktopQuery = window.matchMedia('(min-width: 1024px)');
    const params = new URLSearchParams(window.location.search);
    const requestedNeedId = params.get('need');
    const requestedLocationId = params.get('location');

    const initialNeed = needs.find((need) => need.id === requestedNeedId) ?? needs[0] ?? null;
    state.selectedId = initialNeed?.id ?? null;
    let cardExpanded = desktopQuery.matches;

    const tooltip = L.tooltip({
        className: 'nf-tooltip',
        direction: 'top',
        offset: [0, -12],
    });

    let map = null;
    let mainTileLayer = null;
    let mainTileUrl = '';
    let userMarker = null;
    let userPoint = null;
    let toastTimeout = null;
    let loginReturnFocus = null;
    let mapErrors = 0;
    let mapErrorShown = false;
    let submitReturnFocus = null;
    let locationReturnFocus = null;
    let placeSearchController = null;
    let placeSearchTimer = null;
    let placeSearchSeq = 0;
    let locationSearchController = null;
    let locationSearchTimer = null;
    let locationSearchSeq = 0;
    let resizeObserver = null;
    let disposed = false;
    let topAreaResults = [];
    let topAreaQuery = '';
    let areaPreview = null;
    let selectedArea = null;
    let areaLookupController = null;
    let areaLookupSeq = 0;
    const controller = new AbortController();
    const listenerOptions = { signal: controller.signal };

    // These build on the shared haversine logic and close over the map and the
    // located point so every ranking path agrees on the origin.
    function mapCenterPoint() {
        if (!map) {
            return null;
        }

        const center = map.getCenter();
        const lat = Number(center?.lat);
        const lng = Number(center?.lng);

        return Number.isFinite(lat) && Number.isFinite(lng) ? [lat, lng] : null;
    }

    function currentViewbox() {
        if (!map) {
            return null;
        }

        const bounds = map.getBounds();
        const west = bounds?.getWest?.();
        const north = bounds?.getNorth?.();
        const east = bounds?.getEast?.();
        const south = bounds?.getSouth?.();

        return [west, north, east, south].every(Number.isFinite) ? [west, north, east, south] : null;
    }


    function rankByProximity(items, getPoint) {
        const center = mapCenterPoint();
        const origin = userPoint ?? center;

        return items
            .map((item, index) => {
                const point = getPoint(item);
                const fromOrigin = point && origin
                    ? haversineMeters(origin[0], origin[1], point[0], point[1])
                    : Infinity;
                const fromCenter = !userPoint
                    ? 0
                    : (point && center
                        ? haversineMeters(center[0], center[1], point[0], point[1])
                        : Infinity);

                return { item, index, fromOrigin, fromCenter };
            })
            .sort((first, second) => first.fromOrigin - second.fromOrigin
                || first.fromCenter - second.fromCenter
                || first.index - second.index)
            .map(({ item }) => item);
    }

    // One binding path for the four marker kinds: hover and focus show the
    // label plus the shared tooltip, click or Enter/Space opens the panel.
    function bindMarkerInteractions(marker, meta) {
        if (!marker) {
            return;
        }

        marker.nfMarkerMeta = meta;
        marker.on('click', meta.onSelect);
        bindMarkerDomEvents(marker);
    }

    function bindMarkerDomEvents(marker) {
        const meta = marker?.nfMarkerMeta;
        const element = marker?.getElement();

        if (!meta || !element || meta.nfBoundElement === element) {
            return;
        }

        meta.nfBoundElement = element;
        const root = element.querySelector('.nf-map-marker') ?? element;

        const show = () => {
            root.classList.add('is-hovered');
            showMarkerTooltip(meta);
        };
        const hide = () => {
            root.classList.remove('is-hovered');
            hideMarkerTooltip();
        };

        element.addEventListener('mouseenter', show);
        element.addEventListener('focusin', show);
        element.addEventListener('mouseleave', hide);
        element.addEventListener('focusout', hide);
        element.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            (meta.onKeySelect ?? meta.onSelect)();
        });
    }

    function syncMarkerHtml(marker) {
        const element = marker?.getElement();
        const root = element?.querySelector('.nf-map-marker');

        if (root && marker.options?.icon?.options) {
            marker.options.icon.options.html = root.outerHTML;
        }
    }

    function applyMarkerDeclutter() {
        if (!map) {
            return;
        }

        const zoom = map.getZoom();
        const limit = DECLUTTER_LIMITS[Math.floor(zoom)] ?? Infinity;
        const priority = { need: 0, saved: 1, 'osm-poi': 2, 'osm-place': 0 };
        const entries = [];
        if (areaPreview?.marker) {
            const latLng = areaPreview.marker.getLatLng();
            entries.push({ marker: areaPreview.marker, point: [latLng.lng, latLng.lat], kind: 'osm-place' });
        }

        state.markers.forEach((marker, id) => {
            const need = state.needs.find((item) => item.id === id);
            const point = needPoint(need);

            if (point) {
                entries.push({ marker, point, kind: 'need' });
            }
        });
        state.locationMarkers.forEach((marker, id) => {
            const location = state.locations.find((item) => item.id === id);

            if (location) {
                entries.push({ marker, point: [location.longitude, location.latitude], kind: 'saved' });
            }
        });
        poiMarkers.forEach((marker, id) => {
            const poi = currentPois.find((item) => item.id === id);

            if (poi) {
                entries.push({ marker, point: [poi.lng, poi.lat], kind: 'osm-poi' });
            }
        });

        entries.sort((first, second) => priority[first.kind] - priority[second.kind]);

        const keptPoints = [];
        let kept = 0;

        entries.forEach(({ marker, point }) => {
            const containerPoint = map.latLngToContainerPoint([point[1], point[0]]);
            const tooClose = keptPoints.some((keptPoint) => keptPoint.distanceTo(containerPoint) < DECLUTTER_MIN_DISTANCE_PX);
            const show = limit === Infinity || (kept < limit && !tooClose);

            if (!show) {
                marker.remove();
                return;
            }
            syncMarkerHtml(marker);

            marker.addTo(map);
            bindMarkerDomEvents(marker);

            if (limit !== Infinity) {
                kept += 1;
                keptPoints.push(containerPoint);
            }
        });
    }

    initMap();
    bindEvents();
    setCardExpanded(cardExpanded);
    applyFilters({ fit: true, syncUrl: false });
    setCardExpanded(cardExpanded);

    if (submitSheet?.dataset.initialOpen === 'true') {
        openSubmitSheet();
    }

    // A flash arrives with the redirect back to the map after a submission; it
    // is shown once the map has had a frame to paint, so the toast is not lost
    // behind the initial skeleton.
    if (typeof flash === 'string' && flash.trim() !== '') {
        window.setTimeout(() => {
            if (!disposed) {
                showToast(flash.trim());
            }
        }, 700);
    }

    requestAnimationFrame(() => {
        if (disposed) {
            return;
        }

        if (map) resizeMap(map);

        // A deep link asks for ONE thing. Auto-locating on top of it moved the
        // camera to the visitor a moment later, so the pin they came for drifted
        // away. A `?location=` link wins over a stale `?need=` on the same URL.
        if (requestedLocationId !== null && state.locations.some((location) => location.id === requestedLocationId)) {
            selectLocation(requestedLocationId);
        } else if (requestedNeedId !== null) {
            if (state.selectedId !== null) {
                focusNeedOnMap(state.selectedId);
            }
        } else {
            fitMapToFiltered();
            locateUser({ silent: true });
        }
    });

    // R-27: the status covers the initial style load and every later swap.
    function setMapLoading(loading) {
        if (mapLoading) mapLoading.hidden = !loading;
    }

    function initMap() {
        setMapLoading(true);
        let declutterTimer = null;
        try {
            map = createLeafletMap(mapElement, {
                center: toLeafletLatLng([107.5944, -6.8903]),
                zoom: 15,
                minZoom: 13,
                maxZoom: 19,
            });
            mainTileUrl = themeStyles[currentTheme()];
            mainTileLayer = createTileLayer(mainTileUrl);
            mainTileLayer.addTo(map);
            resizeMap(map);
            setMapLoading(false);
        } catch (error) {
            setMapLoading(false);
            console.error('nearFind: peta gagal dibuat.', error);
            showToast('Peta tidak dapat dimuat di browser ini.');
            return;
        }

        mainTileLayer.on('tileerror', () => {
            mapErrors += 1;

            if (mapErrors >= 4 && !mapErrorShown) {
                mapErrorShown = true;
                setMapLoading(false);
                showToast('Peta tidak dapat memuat tile. Periksa koneksi internetmu.');
            }
        });

        map.on('zoomend', applyMarkerDeclutter);
        map.on('moveend', () => {
            window.clearTimeout(declutterTimer);
            declutterTimer = window.setTimeout(applyMarkerDeclutter, DECLUTTER_MOVE_DEBOUNCE_MS);
        });

        if ('ResizeObserver' in window) {
            resizeObserver = new ResizeObserver(() => {
                if (map) resizeMap(map);
            });
            resizeObserver.observe(mapElement);
        }

        if (poiConfig.enabled) {
            map.on('moveend', schedulePoiRefresh);
            schedulePoiRefresh();
        }
    }

    function bindEvents() {
        searchInput?.addEventListener('input', handleSearchInput);

        document.querySelector('[data-search-box]')?.addEventListener('click', (event) => {
            if (event.target.closest('button') || event.target === searchInput) {
                return;
            }

            searchInput?.focus();
        });
        searchInput?.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && searchInput.value !== '') {
                clearSearch();
            }
        });

        searchForm?.addEventListener('submit', (event) => {
            event.preventDefault();

            // Enter activates the first visible result. With no results it
            // neither filters the card nor touches the camera.
            placeTopResults?.querySelector('[data-search-result]')?.click();
        });

        searchClear?.addEventListener('click', () => {
            clearSearch();
            resetPlaceResults(placeTopResults);
        });

        document.querySelectorAll('.category-chip[data-category]').forEach((button) => {
            button.addEventListener('click', () => {
                state.category = button.dataset.category ?? 'all';
                applyFilters({ fit: true });
            });
        });

        document.addEventListener('click', (event) => {
            const actionElement = event.target.closest('[data-action]');

            if (!actionElement) {
                return;
            }

            switch (actionElement.dataset.action) {
                case 'open-filter':
                    openFilterSheet();
                    break;
                case 'login':
                    openLoginGate('Masuk untuk mengakses akun nearFind.', actionElement);
                    break;
                case 'add-place':
                    if (requireLogin('add-place', actionElement)) {
                        const poi = actionElement.closest('[data-poi-panel]') ? currentSelectedPoi() : null;
                        const fromArea = actionElement.closest('[data-area-panel], .nf-area-preview-shell');
                        const area = fromArea && !poi ? currentSelectedArea() : null;
                        openLocationSheet(actionElement, poi ? poiSheetPrefill(poi) : area ? areaSheetPrefill(area) : null);
                    }
                    break;
                case 'submit-need':
                    if (requireLogin('submit-need', actionElement)) {
                        openSubmitSheet(actionElement);
                    }
                    break;
            }
        }, listenerOptions);

        filterSheet?.querySelectorAll('[data-filter-close]').forEach((button) => {
            button.addEventListener('click', closeFilterSheet);
        });

        filterSheet?.querySelector('[data-filter-backdrop]')?.addEventListener('click', closeFilterSheet);

        loginGate?.querySelectorAll('[data-login-close]').forEach((button) => {
            button.addEventListener('click', closeLoginGate);
        });

        loginGate?.querySelector('[data-login-backdrop]')?.addEventListener('click', closeLoginGate);

        submitSheet?.querySelectorAll('[data-submit-close]').forEach((button) => {
            button.addEventListener('click', closeSubmitSheet);
        });

        submitSheet?.querySelector('[data-submit-backdrop]')?.addEventListener('click', closeSubmitSheet);

        submitSheet?.addEventListener('keydown', (event) => {
            trapFocus(submitSheet, event);
        }, listenerOptions);

        locationSheet?.addEventListener('keydown', (event) => {
            trapFocus(locationSheet, event);
        }, listenerOptions);

        poiPanel?.addEventListener('keydown', (event) => {
            trapFocus(poiPanel, event);
        }, listenerOptions);
        areaPanel?.addEventListener('keydown', (event) => {
            trapFocus(areaPanel, event);
        }, listenerOptions);

        loginGate?.addEventListener('keydown', (event) => {
            trapFocus(loginGate, event);
        }, listenerOptions);

        filterSheet?.addEventListener('keydown', (event) => {
            trapFocus(filterSheet, event);
        }, listenerOptions);

        filterForm?.addEventListener('submit', (event) => {
            event.preventDefault();

            const formData = new FormData(filterForm);
            state.category = String(formData.get('filter-category') ?? 'all');
            state.sort = String(formData.get('filter-sort') ?? 'relevance');

            closeFilterSheet();
            applyFilters({ fit: true });
            showToast('Filter peta diperbarui.');
        });

        document.getElementById('filter-reset')?.addEventListener('click', resetFilters);

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }

            if (poiPanel && !poiPanel.hasAttribute('hidden')) {
                closePoiPanel();
                return;
            }

            if (areaPanel && !areaPanel.hasAttribute('hidden')) {
                clearAreaPreview();
                return;
            }

            if (!locationPanel?.hasAttribute('hidden')) {
                closeLocationPanel();
                return;
            }

            if (locationSheet && !locationSheet.classList.contains('hidden')) {
                closeLocationSheet();
                return;
            }

            if (loginGate && !loginGate.classList.contains('hidden')) {
                closeLoginGate();
                return;
            }

            if (submitSheet && !submitSheet.classList.contains('hidden')) {
                closeSubmitSheet();
                return;
            }

            if (filterSheet && !filterSheet.classList.contains('hidden')) {
                closeFilterSheet();
                return;
            }

            if (!desktopQuery.matches && needPanel?.dataset.state === 'expanded') {
                setCardExpanded(false);
            }
        }, listenerOptions);

        document.querySelector('[data-dismiss-tip]')?.addEventListener('click', () => mapTip?.remove());

        document.querySelector('[data-map-locate]')?.addEventListener('click', () => locateUser());
        document.querySelector('[data-map-zoom-in]')?.addEventListener('click', () => zoomBy(0.5));
        document.querySelector('[data-map-zoom-out]')?.addEventListener('click', () => zoomBy(-0.5));

        needPanel?.addEventListener('click', (event) => {
            if (!event.target.closest('[data-card-toggle]')) {
                return;
            }

            event.preventDefault();
            setCardExpanded(!cardExpanded);
        });

        desktopQuery.addEventListener('change', (event) => {
            setCardExpanded(event.matches);
        }, listenerOptions);

        needCardElement.addEventListener('click', (event) => {
            if (event.target.closest('[data-share-button]')) {
                shareNeed(state.selectedId);
                return;
            }

            if (event.target.closest('[data-reset-filters]')) {
                resetFilters();
            }
        });

        // Nominatim is the only geocoder here. The browser sends the page
        // Referer automatically, which is what the service asks for, and the
        // results below come straight from its JSON response.
        placeTopResults?.addEventListener('click', onTopSearchResultClick, listenerOptions);
        bindPlaceResults(locationResults, (place) => {
            prefillLocationForm(place);
        });

        locationPlaceSearch?.addEventListener('input', scheduleLocationPlaceSearch);
        locationPlaceSearch?.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }

            window.clearTimeout(locationSearchTimer);
            locationSearchController?.abort();
            resetPlaceResults(locationResults);
        });

        locationSheet?.querySelectorAll('[data-location-close]').forEach((button) => {
            button.addEventListener('click', closeLocationSheet);
        });
        locationSheet?.querySelector('[data-location-backdrop]')?.addEventListener('click', closeLocationSheet);
        locationPanelClose?.addEventListener('click', closeLocationPanel);
        poiPanelClose?.addEventListener('click', closePoiPanel);
        areaPanelClose?.addEventListener('click', clearAreaPreview);

        // Validate the picked point, then leave the real POST to the browser.
        // There is no fake success toast or intercepted response here.
        locationForm?.addEventListener('submit', (event) => {
            const lat = Number.parseFloat(locationLatInput?.value ?? '');
            const lng = Number.parseFloat(locationLngInput?.value ?? '');

            if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                event.preventDefault();
                showToast('Pilih titik lokasi pada peta sebelum menyimpan.');
                announce('Pilih titik lokasi pada peta sebelum menyimpan.');
                locationPicker.ensure();
            }
        }, listenerOptions);
    }

    function handleSearchInput() {
        const value = searchInput ? searchInput.value : '';

        setSearchClearVisibility(value.trim() !== '');
        renderTopSearchResults();
        scheduleTopPlaceSearch();
    }

    function setSearchClearVisibility(isVisible) {
        if (!searchClear) {
            return;
        }

        searchClear.classList.toggle('hidden', !isVisible);
        searchClear.style.display = isVisible ? 'flex' : '';
    }

    function clearSearch() {
        if (!searchInput) {
            return;
        }

        searchInput.value = '';
        topAreaResults = [];
        topAreaQuery = '';
        setSearchClearVisibility(false);
        resetPlaceResults(placeTopResults);
        searchInput.focus();
    }

    function resetFilters() {
        state.category = 'all';
        state.sort = 'nearest';
        state.query = '';

        if (searchInput) {
            searchInput.value = '';
        }

        setSearchClearVisibility(false);
        closeFilterSheet();
        resetPlaceResults(placeTopResults);
        showToast('Filter dikembalikan ke semua kebutuhan.');
    }

    function applyFilters({ fit = false, syncUrl = true } = {}) {
        const query = state.query.toLocaleLowerCase('id-ID');

        const filteredNeeds = state.needs.filter((need) => {
            const category = categoryById[need.category] ?? fallbackCategory;
            const matchesCategory = state.category === 'all' || need.category === state.category;

            if (!matchesCategory) {
                return false;
            }

            return matchesWords(
                [need.title, need.marker_label, need.location, need.area, need.description, category.label].join(' '),
                query,
            );
        });

        state.filtered = sortNeeds(filteredNeeds);

        if (!state.filtered.some((need) => need.id === state.selectedId)) {
            state.selectedId = state.filtered[0]?.id ?? null;
        }

        renderMarkers();
        renderCard();
        syncChips();
        syncFilterIndicator();

        if (syncUrl) {
            syncUrlSelection();
        }

        if (fit) {
            fitMapToFiltered();
        }
    }

    /**
     * Whole hours between a row's `created_at` and now. The server sends the
     * timestamp and the browser does the arithmetic, so the compiled PHP side
     * never needs a Carbon method the rung does not implement.
     */
    function ageInHours(value) {
        const then = Date.parse(value || '');
        if (Number.isNaN(then)) return 0;
        return Math.max(0, Math.floor((Date.now() - then) / 3600000));
    }

    function sortNeeds(filteredNeeds) {
        let sortedNeeds = [...filteredNeeds];

        switch (state.sort) {
            case 'newest':
                // The row's timestamp, not a pre-computed age: the server no
                // longer rounds it (the compiled rung has no Carbon surface).
                sortedNeeds.sort((first, second) => ageInHours(second.created_at) - ageInHours(first.created_at));
                break;
            case 'supported':
                sortedNeeds.sort((first, second) => second.supporters - first.supporters);
                break;
            case 'nearest':
                sortedNeeds = rankByProximity(sortedNeeds, (need) => {
                    const point = needPoint(need);

                    return point ? [point[1], point[0]] : null;
                });
                break;
            default:
                break;
        }

        return sortedNeeds;
    }

    function syncChips() {
        document.querySelectorAll('.category-chip[data-category]').forEach((button) => {
            const isActive = button.dataset.category === state.category;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    function syncFilterIndicator() {
        const hasActiveFilter = state.category !== 'all' || state.sort !== 'relevance';
        filterDot?.classList.toggle('hidden', !hasActiveFilter);
    }

    function syncFilterForm() {
        filterForm?.querySelectorAll('input[name="filter-category"]').forEach((input) => {
            input.checked = input.value === state.category;
        });

        filterForm?.querySelectorAll('input[name="filter-sort"]').forEach((input) => {
            input.checked = input.value === state.sort;
        });
    }

    function openFilterSheet() {
        if (!filterSheet) {
            return;
        }

        syncFilterForm();
        filterSheet.classList.remove('hidden');
        filterSheet.classList.add('flex');

        requestAnimationFrame(() => {
            filterSheet.querySelector('input[name="filter-category"]:checked')?.focus({ preventScroll: true });
        });
    }

    function closeFilterSheet() {
        if (!filterSheet) {
            return;
        }

        const wasOpen = !filterSheet.classList.contains('hidden');
        filterSheet.classList.add('hidden');
        filterSheet.classList.remove('flex');

        if (wasOpen) {
            filterOpenButton?.focus({ preventScroll: true });
        }
    }

    function requireLogin(action, trigger = null) {
        if (state.authenticated) {
            return true;
        }

        const messages = {
            support: 'Masuk dulu untuk menambahkan dukunganmu pada kebutuhan ini.',
            report: 'Masuk dulu untuk mengirim laporan ketidaksesuaian.',
            'submit-need': 'Masuk dulu untuk mengajukan kebutuhan baru di peta.',
            'add-place': 'Masuk dulu untuk menyimpan tempat baru di peta.',
        };

        openLoginGate(messages[action] ?? 'Masuk atau daftar untuk melanjutkan.', trigger);

        return false;
    }

    function openLoginGate(message, trigger = null) {
        if (!loginGate) {
            return;
        }

        loginReturnFocus = trigger instanceof HTMLElement ? trigger : document.activeElement;

        if (loginMessage) {
            loginMessage.textContent = message;
        }

        loginGate.classList.remove('hidden');
        loginGate.classList.add('flex');

        requestAnimationFrame(() => {
            loginSubmit?.focus({ preventScroll: true });
        });
    }

    function closeLoginGate() {
        if (!loginGate || loginGate.classList.contains('hidden')) {
            return;
        }

        loginGate.classList.add('hidden');
        loginGate.classList.remove('flex');

        if (loginReturnFocus instanceof HTMLElement) {
            loginReturnFocus.focus({ preventScroll: true });
        }

        loginReturnFocus = null;
    }

    function openSubmitSheet(trigger = null) {
        if (!submitSheet) {
            return;
        }
        closePoiPanel();

        const centerPoint = reticlePoint();

        if (centerPoint) {
            setPickerPoint(needLatInput, needLngInput, centerPoint);
        }

        closeFilterSheet();
        submitReturnFocus = trigger instanceof HTMLElement ? trigger : document.activeElement;

        submitSheet.classList.remove('hidden');
        submitSheet.classList.add('flex');

        requestAnimationFrame(() => {
            submitSheet.querySelector('#need-title')?.focus({ preventScroll: true });
        });

        // The map inside the sheet cannot be laid out while it is `display:none`,
        // so it is only created once the sheet is actually on screen.
        const created = needPicker.ensure();

        if (!created && centerPoint) {
            needPicker.centerOn(centerPoint[1], centerPoint[0]);
        }
    }

    function closeSubmitSheet() {
        if (!submitSheet || submitSheet.classList.contains('hidden')) {
            return;
        }

        submitSheet.classList.add('hidden');
        submitSheet.classList.remove('flex');

        if (submitReturnFocus instanceof HTMLElement) {
            submitReturnFocus.focus({ preventScroll: true });
        }

        submitReturnFocus = null;
    }

    // --- location pickers ---------------------------------------------------
    // Both sheets choose a point the same way: the pin stays at the map centre
    // and the map moves under it. One factory, two scoped instances, so a press
    // in the need sheet can never touch the location sheet's hidden inputs.
    const needLatInput = submitSheet?.querySelector('[data-point-lat]');
    const needLngInput = submitSheet?.querySelector('[data-point-lng]');

    /** The point already in the form, else the need being viewed, else the city. */
    function storedPoint() {
        const lat = Number.parseFloat(needLatInput?.value ?? '');
        const lng = Number.parseFloat(needLngInput?.value ?? '');

        if (Number.isFinite(lat) && Number.isFinite(lng)) {
            return [lng, lat];
        }

        const selected = state.needs.find((need) => need.id === state.selectedId);

        const point = needPoint(selected);

        if (point) {
            return point;
        }

        return [107.5944, -6.8903];
    }

    /** The visual reticle was removed; the map centre remains the capture point for a sheet. */
    function reticlePoint() {
        const center = mapCenterPoint();

        return center ? [center[1], center[0]] : null;
    }

    /** Write a [lng, lat] point into the hidden inputs the sheet and picker read. */
    function setPickerPoint(latInput, lngInput, [lng, lat]) {
        if (latInput) latInput.value = lat.toFixed(6);
        if (lngInput) lngInput.value = lng.toFixed(6);
    }

    const needPicker = createPointPicker({
        root: submitSheet,
        picker: submitSheet?.querySelector('[data-point-picker]'),
        canvas: submitSheet?.querySelector('[data-point-canvas]'),
        hint: submitSheet?.querySelector('[data-point-hint]'),
        summary: submitSheet?.querySelector('[data-point-summary]'),
        busy: submitSheet?.querySelector('[data-point-busy]'),
        latInput: needLatInput,
        lngInput: needLngInput,
        areaInput: submitSheet?.querySelector('#need-area'),
        stepAttr: 'pointStep',
        zoomAttr: 'pointZoom',
        locateAttr: 'pointLocate',
        initialPoint: storedPoint,
        styleUrl: () => themeStyles[currentTheme()],
        summarySuffix: 'pin tepat di lokasi kebutuhan',
        signal: controller.signal,
        notify: showToast,
    });
    needPicker.bind();

    const locationPicker = createPointPicker({
        root: locationSheet,
        picker: locationSheet?.querySelector('[data-location-picker]'),
        canvas: locationSheet?.querySelector('[data-location-point-canvas]'),
        summary: locationSheet?.querySelector('[data-location-point-summary]'),
        busy: locationSheet?.querySelector('[data-location-point-busy]'),
        latInput: locationLatInput,
        lngInput: locationLngInput,
        areaInput: locationAreaInput,
        stepAttr: 'locationPointStep',
        zoomAttr: 'locationPointZoom',
        locateAttr: 'locationPointLocate',
        initialPoint: () => {
            const lat = Number.parseFloat(locationLatInput?.value ?? '');
            const lng = Number.parseFloat(locationLngInput?.value ?? '');
            return Number.isFinite(lat) && Number.isFinite(lng) ? [lng, lat] : [107.5944, -6.8903];
        },
        styleUrl: () => themeStyles[currentTheme()],
        summarySuffix: 'titik lokasi yang akan disimpan',
        signal: controller.signal,
        notify: showToast,
    });
    locationPicker.bind();

    document.addEventListener('nearfind:themechange', (event) => {
        const styleUrl = event?.detail?.styleUrl ?? themeStyles[currentTheme()];
        try {
            if (map && mainTileLayer && styleUrl && styleUrl !== mainTileUrl) {
                mainTileUrl = styleUrl;
                mainTileLayer.setUrl(styleUrl);
            }
        } catch {
            // A tile URL swap during a camera transition is harmless.
        }
        needPicker.setStyle(styleUrl);
        locationPicker.setStyle(styleUrl);
    }, listenerOptions);

    function setCardExpanded(expanded) {
        if (!needPanel) {
            return;
        }

        cardExpanded = expanded;
        needPanel.dataset.state = expanded ? 'expanded' : 'collapsed';
        mapControls?.classList.toggle('is-hidden', !desktopQuery.matches && expanded);

        needPanel.querySelectorAll('[data-card-toggle]').forEach((toggle) => {
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            toggle.setAttribute('aria-label', expanded ? 'Tutup detail kebutuhan' : 'Buka detail kebutuhan');

            const label = toggle.querySelector('[data-card-toggle-label]');

            if (label) {
                label.textContent = expanded ? 'Tutup' : 'Detail';
            }
        });
    }

    function renderMarkers() {
        if (disposed || !map) {
            return;
        }

        tooltip.remove();
        state.markers.forEach((marker) => marker.remove());
        state.markers.clear();

        state.filtered.forEach((need) => {
            const point = needPoint(need);

            if (!point) {
                return;
            }
            const element = createMarkerElement(need, need.id === state.selectedId);
            const marker = L.marker(toLeafletLatLng(point), {
                icon: L.divIcon({
                    html: element.outerHTML,
                    className: 'nf-leaflet-icon',
                    iconSize: [44, 44],
                    iconAnchor: [15, 44],
                }),
                keyboard: false,
                riseOnHover: true,
            }).addTo(map);

            bindMarkerInteractions(marker, {
                point,
                label: need.marker_label,
                source: 'Pengajuan warga',
                supporters: displaySupporters(need),
                onSelect: () => selectNeed(need.id),
            });

            state.markers.set(need.id, marker);
        });

        renderLocationMarkers();
        applyMarkerDeclutter();
    }

    function createMarkerElement(need, isSelected) {
        const element = document.createElement('div');
        const categoryId = need.category ?? '';
        element.className = 'nf-marker-wrapper nf-map-marker nf-map-marker--need';
        element.setAttribute('role', 'button');
        element.setAttribute('tabindex', '0');
        element.setAttribute('data-marker-kind', 'need');
        element.setAttribute('data-source', 'Pengajuan warga');
        element.setAttribute('data-category', String(categoryId));
        element.setAttribute('data-shape', String(categoryId || 'osm'));
        element.style.minWidth = '44px';
        element.style.minHeight = '44px';
        element.style.zIndex = isSelected ? '420' : '';
        element.innerHTML = markerMarkup(need, isSelected);
        updateMarkerAria(element, need);

        return element;
    }

    function markerMarkup(need, isSelected) {
        const category = categoryById[need.category] ?? fallbackCategory;
        const supporters = displaySupporters(need);
        const markerStyle = `--marker-color:${category.color}`;

        return `<span class="nf-map-marker__pin" style="${markerStyle}">${icon(category.icon, 'size-3.5')}</span>
                <div class="nf-map-marker__label ${isSelected ? 'is-selected' : ''}" style="${markerStyle}">
                    <span class="nf-map-marker__title">${escapeHtml(need.marker_label)}</span>
                    <span class="nf-map-marker__count">${supporters}</span>
                    <span class="nf-map-marker__source">Pengajuan warga</span>
                </div>`;
    }

    function updateMarkerAria(element, need) {
        const category = categoryById[need.category] ?? fallbackCategory;
        element.setAttribute(
            'aria-label',
            `${need.title}, kategori ${category.label}, ${displaySupporters(need)} warga mendukung`,
        );
    }

    function showMarkerTooltip({ point, label, source, supporters = null }) {
        if (disposed || !map || !point) {
            return;
        }

        const count = supporters === null ? '' : ` · ${supporters} warga`;
        tooltip
            .setLatLng(toLeafletLatLng(point))
            .setContent(escapeHtml(`${label} · ${source}${count}`))
            .addTo(map);
    }

    function hideMarkerTooltip() {
        tooltip.remove();
    }

    // --- OpenStreetMap POI overlay -----------------------------------------
    function schedulePoiRefresh() {
        if (disposed || !poiConfig.enabled || !map) {
            return;
        }

        window.clearTimeout(poiTimer);
        poiTimer = window.setTimeout(refreshPoiMarkers, POI_REFRESH_DEBOUNCE_MS);
    }

    async function refreshPoiMarkers() {
        if (disposed || !poiConfig.enabled || !map) {
            return;
        }

        window.clearTimeout(poiTimer);
        const center = map.getCenter();
        const key = poiCacheKey(center);

        if (key === '') {
            return;
        }

        const cached = poiCache.get(key);

        if (cached && Date.now() - cached.at < poiConfig.cache_ms) {
            renderPoiMarkers(cached.pois);
            return;
        }

        if (cached) {
            poiCache.delete(key);
        }

        const seq = ++poiSeq;
        poiController?.abort();
        poiController = new AbortController();
        const controller = poiController;
        const pois = await fetchOverpassPois(center, { signal: controller.signal });

        if (disposed || seq !== poiSeq || controller !== poiController || !map) {
            return;
        }

        poiCache.forEach((entry, entryKey) => {
            if (Date.now() - entry.at >= poiConfig.cache_ms) {
                poiCache.delete(entryKey);
            }
        });
        poiCache.set(key, { at: Date.now(), pois });
        renderPoiMarkers(pois);
    }

    function renderPoiMarkers(pois) {
        if (disposed || !map) {
            return;
        }

        poiMarkers.forEach((marker) => marker.remove());
        poiMarkers.clear();
        currentPois = rankByProximity(Array.isArray(pois) ? pois : [], (poi) => [poi.lat, poi.lng]);

        if (selectedPoiId !== null && !currentPois.some((poi) => poi.id === selectedPoiId)) {
            closePoiPanel();
        }

        // Landmarks lead the POI display; proximity still orders places within each group.
        const displayPois = [...currentPois].sort((first, second) => Number(isLandmarkPoi(second)) - Number(isLandmarkPoi(first)));

        displayPois.forEach((poi) => {
            const wrapper = document.createElement('div');
            const categoryId = poiCategoryId(poi.tags) ?? '';
            const category = categoryById[categoryId] ?? fallbackCategory;
            const markerStyle = `--marker-color:${category.color}`;
            wrapper.className = 'nf-marker-wrapper nf-poi-marker-wrapper nf-map-marker nf-map-marker--osm-poi';
            wrapper.setAttribute('role', 'button');
            wrapper.setAttribute('tabindex', '0');
            wrapper.setAttribute('data-marker-kind', 'osm-poi');
            wrapper.setAttribute('data-source', 'OpenStreetMap');
            wrapper.setAttribute('data-category', String(categoryId));
            wrapper.setAttribute('data-shape', String(categoryId || 'osm'));
            wrapper.setAttribute('aria-label', `${poi.name}, ${poi.category}, titik dari OpenStreetMap`);
            wrapper.innerHTML = `<span class="nf-map-marker__pin" style="${markerStyle}">${icon(category.icon ?? 'pin', 'size-3.5')}</span>
                    <div class="nf-map-marker__label" style="${markerStyle}">
                        <span class="nf-map-marker__title">${escapeHtml(poi.name)}</span>
                        <span class="nf-map-marker__source">OpenStreetMap</span>
                    </div>`;

            const marker = L.marker([poi.lat, poi.lng], {
                icon: L.divIcon({
                    html: wrapper.outerHTML,
                    className: 'nf-leaflet-icon nf-poi-icon',
                    iconSize: [44, 44],
                    iconAnchor: [15, 44],
                }),
                keyboard: false,
                riseOnHover: false,
            }).addTo(map);

            // Need markers use 420 and location markers 430, so this layer
            // always paints underneath both of them.
            marker.setZIndexOffset(-1000);

            bindMarkerInteractions(marker, {
                point: [poi.lng, poi.lat],
                label: poi.name,
                source: 'OpenStreetMap',
                onSelect: () => selectPoi(poi.id),
            });

            poiMarkers.set(poi.id, marker);
        });
        applyMarkerDeclutter();
    }

    function currentSelectedPoi() {
        return currentPois.find((poi) => poi.id === selectedPoiId) ?? null;
    }

    function poiSheetPrefill(poi) {
        return {
            name: poi.name,
            address: poi.address || '',
            area: poi.area || '',
            lat: poi.lat,
            lng: poi.lng,
            category: poiCategoryId(poi.tags),
        };
    }

    function selectPoi(poiId) {
        if (disposed) {
            return;
        }

        const poi = currentPois.find((item) => item.id === poiId);

        if (!poi) {
            return;
        }

        clearAreaPreview();
        closeLocationPanel();
        hideMarkerTooltip();
        state.selectedId = null;
        state.selectedLocationId = null;
        state.filtered.forEach((filteredNeed) => refreshMarker(filteredNeed));
        state.locationMarkers.forEach((marker, id) => refreshLocationMarker(id));
        selectedPoiId = poi.id;
        setPoiPanel(poi);
        syncUrlSelection();

        if (map) {
            panTo(map, [poi.lat, poi.lng], 0.35);
        }

        announce(`${poi.name} dipilih dari OpenStreetMap.`);
        maybeReversePoi(poi);
    }

    function setPoiPanel(poi) {
        if (!poiPanel) {
            return;
        }

        poiPanel.removeAttribute('hidden');
        poiPanel.setAttribute('aria-hidden', 'false');

        if (poiPanelTitle) {
            poiPanelTitle.textContent = poi.name;
        }

        if (poiPanelMeta) {
            poiPanelMeta.textContent = `${poi.category} · OpenStreetMap`;
        }

        renderPoiPanelBody(poi);

        if (poiRouteLink) {
            poiRouteLink.href = `https://www.openstreetmap.org/directions?route=;${poi.lat.toFixed(6)},${poi.lng.toFixed(6)}`;
            poiRouteLink.target = '_blank';
            poiRouteLink.rel = 'noopener noreferrer';
            poiRouteLink.textContent = 'Buka rute';
        }

        if (needPanel) {
            needPanel.hidden = true;
        }

        if (locationPanel) {
            locationPanel.setAttribute('hidden', '');
            locationPanel.setAttribute('aria-hidden', 'true');
        }
    }

    function renderPoiPanelBody(poi) {
        if (!poiPanelBody) {
            return;
        }

        poiPanelBody.textContent = '';
        const rows = [
            ['Kategori', poi.category],
            ['Alamat', poi.address || 'Alamat belum tersedia.'],
            ['Kode pos', poi.postcode || 'Kode pos belum tersedia.'],
            ['Telepon', poi.phone || 'Nomor telepon belum tersedia.'],
            ['Situs web', poi.website || 'Situs web belum tersedia.'],
            ['Jam buka', poi.hours || 'Jam buka belum tercantum.'],
            ['Koordinat', `${poi.lat.toFixed(6)}, ${poi.lng.toFixed(6)}`],
        ];

        rows.forEach(([label, value]) => {
            const row = document.createElement('div');
            row.className = 'nf-poi-row';

            const key = document.createElement('dt');
            key.className = 'nf-poi-row-label';
            key.textContent = label;

            const detail = document.createElement('dd');
            detail.className = 'nf-poi-row-value';
            detail.textContent = value;

            row.append(key, detail);
            poiPanelBody.append(row);
        });
    }

    function closePoiPanel() {
        selectedPoiId = null;

        if (!poiPanel || poiPanel.hasAttribute('hidden')) {
            return;
        }

        window.clearTimeout(poiReverseTimer);
        poiReverseController?.abort();
        poiPanel.setAttribute('hidden', '');
        poiPanel.setAttribute('aria-hidden', 'true');

        if (needPanel) {
            needPanel.hidden = false;
            setCardExpanded(cardExpanded);
        }
    }

    function maybeReversePoi(poi) {
        if (reverseCache.has(poi.id)) {
            applyPoiReverse(poi, reverseCache.get(poi.id));
            return;
        }

        window.clearTimeout(poiReverseTimer);
        poiReverseController?.abort();
        poiReverseController = new AbortController();
        const controller = poiReverseController;

        const run = () => {
            if (disposed || selectedPoiId !== poi.id || controller !== poiReverseController) {
                return;
            }

            lastPoiReverseAt = Date.now();
            reverseGeocodePoi(poi, controller.signal).then((detail) => {
                if (disposed || selectedPoiId !== poi.id || controller !== poiReverseController) {
                    return;
                }

                reverseCache.set(poi.id, detail);
                applyPoiReverse(poi, detail);
            });
        };

        const wait = Math.max(0, POI_REVERSE_MIN_INTERVAL_MS - (Date.now() - lastPoiReverseAt));

        if (wait > 0) {
            poiReverseTimer = window.setTimeout(run, wait);
        } else {
            run();
        }
    }

    function applyPoiReverse(poi, detail) {
        if (detail) {
            if (!poi.address) poi.address = detail.address;
            if (!poi.postcode) poi.postcode = detail.postcode;
            if (!poi.area) poi.area = detail.area;
        }

        if (!disposed && selectedPoiId === poi.id) {
            renderPoiPanelBody(poi);
        }
    }

    function selectNeed(needId, { pan = true, preserveScroll = false } = {}) {
        const need = state.needs.find((item) => item.id === needId);

        if (!need) {
            return;
        }

        const point = needPoint(need);

        closePoiPanel();
        clearAreaPreview();
        closeLocationPanel();
        state.selectedLocationId = null;
        state.selectedId = need.id;

        state.filtered.forEach((filteredNeed) => refreshMarker(filteredNeed));

        mapTip?.remove();
        renderCard({ preserveScroll });
        syncUrlSelection();

        if (point) {
            if (pan && map) {
                panTo(map, toLeafletLatLng(point), 0.35);
            }
            announce(`${need.title} dipilih di peta.`);
        } else {
            hideMarkerTooltip();
            announce(`${need.title} dipilih. Kebutuhan ini belum memiliki titik di peta.`);
        }
    }

    function findSavedLocation(place) {
        const wanted = normalizeName(place.name);
        const byName = state.locations.find((location) => normalizeName(location.name) === wanted);

        if (byName) {
            return byName;
        }

        return state.locations.find((location) => haversineMeters(
            location.latitude,
            location.longitude,
            place.lat,
            place.lng,
        ) <= 150) ?? null;
    }

    function onTopPlaceSelected(place) {
        if (!Number.isFinite(place.lat) || !Number.isFinite(place.lng)) {
            return;
        }

        resetPlaceResults(placeTopResults);

        if (searchInput) {
            searchInput.value = place.name;
            searchInput.blur();
        }

        closeLocationSheet();
        clearAreaPreview();

        // A result press must move the camera deterministically, not start an
        // animation that can stall under frame throttling.
        if (map) {
            setViewWithPadding(map, [place.lat, place.lng]);
        }

        const saved = place.kindKey === 'place' ? findSavedLocation(place) : null;

        if (saved) {
            selectLocation(saved.id);
            return;
        }

        showAreaPreview(place);
    }

    function scheduleTopPlaceSearch() {
        window.clearTimeout(placeSearchTimer);
        placeSearchTimer = window.setTimeout(runTopPlaceSearch, PLACE_SEARCH_DEBOUNCE_MS);
    }

    function runTopPlaceSearch() {
        if (disposed || !searchInput) {
            return;
        }

        const query = searchInput.value.trim();
        window.clearTimeout(placeSearchTimer);
        placeSearchController?.abort();

        if (query.length < PLACE_SEARCH_MIN_CHARS) {
            topAreaResults = [];
            topAreaQuery = query;
            renderTopSearchResults();
            return;
        }

        const seq = ++placeSearchSeq;
        topAreaResults = [];
        topAreaQuery = '';
        renderTopSearchResults({ status: 'loading' });
        placeSearchController = new AbortController();
        const { signal } = placeSearchController;

        const viewbox = currentViewbox();
        fetchNominatimPlaces(query, { signal, viewbox })
            .then((results) => {
                if (disposed || seq !== placeSearchSeq || searchInput.value.trim() !== query) {
                    return;
                }

                topAreaResults = rankByProximity(results, (place) => [place.lat, place.lng]).slice(0, NOMINATIM_RESULT_LIMIT);
                topAreaQuery = query;
                renderTopSearchResults();
            })
            .catch((error) => {
                if (error?.name === 'AbortError' || disposed || seq !== placeSearchSeq) {
                    return;
                }

                renderTopSearchResults({ status: 'error' });
            });
    }

    function makeSearchResultButton({ kind, title, meta, iconName, iconClass, iconStyle = '', ariaLabel, dataset = {} }) {
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.searchResult = kind;
        button.setAttribute('aria-label', ariaLabel);
        button.className = 'flex min-h-[44px] w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40';

        Object.entries(dataset).forEach(([key, value]) => {
            button.dataset[key] = value;
        });

        button.innerHTML = `
            <span class="flex size-8 shrink-0 items-center justify-center rounded-xl ${iconClass}"${iconStyle ? ` style="${iconStyle}"` : ''}>
                ${icon(iconName, 'size-4')}
            </span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-[12px] font-semibold text-ink">${escapeHtml(title)}</span>
                <span class="block truncate text-[10.5px] leading-snug text-muted">${escapeHtml(meta)}</span>
            </span>
        `;

        return button;
    }

    function renderTopSearchResults({ status = 'ready' } = {}) {
        if (!placeTopResults || !searchInput) {
            return;
        }

        const query = searchInput.value.trim();

        if (query === '') {
            resetPlaceResults(placeTopResults);
            return;
        }

        const words = query.toLocaleLowerCase('id-ID');
        // Rank by proximity before capping so a nearer item is never discarded by source order.
        const places = rankByProximity(
            state.locations.filter((location) => matchesWords([location.name, location.address, location.area].join(' '), words)),
            (location) => [location.latitude, location.longitude],
        ).slice(0, NOMINATIM_RESULT_LIMIT);
        const needs = rankByProximity(
            state.needs.filter((need) => matchesWords(
                [need.title, need.marker_label, need.location, need.area, need.description].join(' '),
                words,
            )),
            (need) => {
                const point = needPoint(need);
                return point ? [point[1], point[0]] : null;
            },
        ).slice(0, NOMINATIM_RESULT_LIMIT);
        const pois = rankByProximity(
            currentPois.filter((poi) => matchesWords([poi.name, poi.category, poi.address, poi.area].join(' '), words)),
            (poi) => [poi.lat, poi.lng],
        ).slice(0, NOMINATIM_RESULT_LIMIT);
        const areas = topAreaQuery === query ? topAreaResults : [];

        const rows = [];

        places.forEach((location) => {
            const category = categoryById[location.category] ?? fallbackCategory;
            rows.push({
                point: [location.latitude, location.longitude],
                button: makeSearchResultButton({
                    kind: 'location',
                    title: location.name,
                    meta: `Tempat tersimpan · ${[category?.label, location.address || location.area].filter(Boolean).join(' · ')}`,
                    iconName: 'pin',
                    iconClass: 'bg-brand-50 text-brand-200',
                    ariaLabel: `Pilih tempat tersimpan ${location.name} · Tempat tersimpan`,
                    dataset: { locationId: location.id },
                }),
            });
        });

        needs.forEach((need) => {
            const category = categoryById[need.category] ?? fallbackCategory;
            const point = needPoint(need);
            const detail = point
                ? `${need.location} · ${category?.label ?? 'Kebutuhan'}`
                : `${need.location} · belum ada titik peta`;
            rows.push({
                point: point ? [point[1], point[0]] : null,
                button: makeSearchResultButton({
                    kind: 'need',
                    title: need.title,
                    meta: `Pengajuan warga · ${detail}`,
                    iconName: 'clipboard',
                    iconClass: '',
                    iconStyle: `background:${category?.soft_color ?? 'var(--color-brand-50)'};color:${category?.color ?? 'var(--color-brand-200)'}`,
                    ariaLabel: `Pilih pengajuan warga ${need.title} · Pengajuan warga`,
                    dataset: { needId: need.id },
                }),
            });
        });

        pois.forEach((poi) => {
            rows.push({
                point: [poi.lat, poi.lng],
                button: makeSearchResultButton({
                    kind: 'poi',
                    title: poi.name,
                    meta: `OpenStreetMap · ${poi.category || 'Tempat'}`,
                    iconName: 'pin',
                    iconClass: 'bg-canvas text-muted',
                    ariaLabel: `Pilih ${poi.name} · OpenStreetMap`,
                    dataset: { poiId: poi.id },
                }),
            });
        });

        areas.forEach((area) => {
            rows.push({
                point: [area.lat, area.lng],
                button: makeSearchResultButton({
                    kind: 'area',
                    title: area.name,
                    meta: `OpenStreetMap · ${area.kindLabel} · ${area.address}`,
                    iconName: 'map',
                    iconClass: 'bg-canvas text-muted',
                    ariaLabel: `Pilih ${area.kindLabel} ${area.name} dari OpenStreetMap`,
                    dataset: placeDataset(area),
                }),
            });
        });

        const rankedRows = rankByProximity(rows, (row) => row.point);

        const list = placeTopResults.querySelector(TOP_RESULT_HOOKS.list);
        if (list) {
            list.textContent = '';
            rankedRows.forEach(({ button }) => {
                const row = document.createElement('li');
                row.append(button);
                list.append(row);
            });
        }

        const total = rankedRows.length;
        const isPending = query.length >= PLACE_SEARCH_MIN_CHARS && status === 'loading';
        setChildHidden(placeTopResults, TOP_RESULT_HOOKS.loading, !isPending);
        setChildHidden(placeTopResults, TOP_RESULT_HOOKS.error, status !== 'error');
        setChildHidden(placeTopResults, TOP_RESULT_HOOKS.empty, status !== 'ready' || total !== 0);
        setChildHidden(placeTopResults, TOP_RESULT_HOOKS.list, total === 0);
        placeTopResults.removeAttribute('hidden');
    }

    function onTopSearchResultClick(event) {
        const button = event.target.closest('[data-search-result]');

        if (!button || !placeTopResults?.contains(button)) {
            return;
        }

        event.preventDefault();

        if (button.dataset.searchResult === 'need') {
            const need = state.needs.find((item) => item.id === button.dataset.needId);

            resetPlaceResults(placeTopResults);
            if (searchInput) {
                searchInput.value = need?.title ?? '';
                searchInput.blur();
            }
            selectNeed(button.dataset.needId);
            return;
        }

        if (button.dataset.searchResult === 'location') {
            const location = state.locations.find((item) => item.id === button.dataset.locationId);

            resetPlaceResults(placeTopResults);
            if (searchInput) {
                searchInput.value = location?.name ?? '';
                searchInput.blur();
            }
            selectLocation(button.dataset.locationId);
            return;
        }

        if (button.dataset.searchResult === 'poi') {
            resetPlaceResults(placeTopResults);
            selectPoi(button.dataset.poiId);
            return;
        }

        onTopPlaceSelected(placeFromDataset(button.dataset));
    }

    function currentSelectedArea() {
        return selectedArea;
    }

    function areaSheetPrefill(place) {
        return {
            name: place.name,
            address: place.address || '',
            area: place.area || '',
            lat: place.lat,
            lng: place.lng,
            category: place.categoryId || '',
        };
    }

    function clearAreaPreview() {
        areaLookupSeq += 1;
        areaLookupController?.abort();
        areaLookupController = null;

        if (areaPreview) {
            areaPreview.popup?.remove();
            areaPreview.geometry?.remove();
            areaPreview.marker?.remove();
            areaPreview = null;
        }

        closeAreaPanel();
        selectedArea = null;
    }

    function showAreaPreview(place) {
        if (disposed || !map) {
            return;
        }

        clearAreaPreview();
        closePoiPanel();

        const element = document.createElement('div');
        const categoryId = place.categoryId || '';
        const category = categoryById[categoryId] ?? fallbackCategory;
        const markerStyle = `--marker-color:${category.color}`;
        element.className = 'nf-area-marker-wrapper nf-map-marker nf-map-marker--osm-place';
        element.setAttribute('role', 'button');
        element.setAttribute('tabindex', '0');
        element.setAttribute('data-marker-kind', 'osm-place');
        element.setAttribute('data-source', 'OpenStreetMap');
        element.setAttribute('data-category', String(categoryId));
        element.setAttribute('data-shape', String(categoryId || 'osm'));
        element.setAttribute('aria-label', `Area ${place.name} di peta`);
        element.style.minWidth = '44px';
        element.style.minHeight = '44px';
        element.innerHTML = `<span class="nf-map-marker__pin" style="${markerStyle}">${icon(category.icon ?? 'map', 'size-4')}</span>
                    <div class="nf-map-marker__label" style="${markerStyle}">
                        <span class="nf-map-marker__title">${escapeHtml(place.name)}</span>
                        <span class="nf-map-marker__source">OpenStreetMap</span>
                    </div>`;

        const marker = L.marker([place.lat, place.lng], {
            icon: L.divIcon({
                html: element.outerHTML,
                className: 'nf-leaflet-icon',
                iconSize: [44, 44],
                iconAnchor: [15, 44],
            }),
            keyboard: false,
            riseOnHover: true,
        }).addTo(map);
        const popup = createAreaPopup(place).setLatLng([place.lat, place.lng]);

        const openPanel = () => {
            if (selectedArea) {
                setAreaPanel(selectedArea);
            }

            popup.addTo(map);
        };

        bindMarkerInteractions(marker, {
            point: [place.lng, place.lat],
            label: place.name,
            source: 'OpenStreetMap',
            onSelect: openPanel,
        });

        selectedArea = place;
        areaPreview = { marker, popup, geometry: renderAreaGeometry(place) };
        setAreaPanel(place);
        fetchAreaDetails(place);
    }

    function setAreaPanel(place, { loading = false } = {}) {
        if (!areaPanel || !place) {
            return;
        }

        areaPanel.removeAttribute('hidden');
        areaPanel.setAttribute('aria-hidden', 'false');

        if (areaPanelTitle) {
            areaPanelTitle.textContent = place.name;
        }

        if (areaPanelMeta) {
            areaPanelMeta.textContent = `${place.kindLabel} · OpenStreetMap`;
        }

        if (areaPanelKicker) {
            areaPanelKicker.textContent = place.kindKey === 'place'
                ? 'Tempat dari OpenStreetMap'
                : 'Daerah dari OpenStreetMap';
        }

        renderAreaPanelBody(place, loading);

        const path = osmObjectPath(place);

        if (areaRouteLink) {
            if (path) {
                areaRouteLink.href = `https://www.openstreetmap.org/${path}`;
                areaRouteLink.textContent = 'Buka di OpenStreetMap';
            }

            areaRouteLink.target = '_blank';
            areaRouteLink.rel = 'noopener noreferrer';
        }

        if (needPanel) {
            needPanel.hidden = true;
        }

        if (locationPanel) {
            locationPanel.setAttribute('hidden', '');
            locationPanel.setAttribute('aria-hidden', 'true');
        }
    }

    function renderAreaPanelBody(place, loading) {
        if (!areaPanelBody) {
            return;
        }

        areaPanelBody.textContent = '';

        const coordinates = Number.isFinite(place.lat) && Number.isFinite(place.lng)
            ? `${place.lat.toFixed(6)}, ${place.lng.toFixed(6)}`
            : 'Koordinat belum tersedia.';
        const rows = [
            ['Jenis', place.kindLabel || OSM_KIND_LABELS.area],
            ...(categoryById[place.categoryId]
                ? [['Kategori nearFind', categoryById[place.categoryId].label]]
                : []),
            ['Jam buka', place.openingHours || 'Jam buka belum tercantum.'],
            ['Alamat', place.address || 'Alamat belum tersedia.'],
            ['Wilayah', place.area || 'Wilayah belum tersedia.'],
            ['Kode pos', place.postcode || 'Kode pos belum tersedia.'],
            ['Negara', place.country || 'Negara belum tersedia.'],
            ['Situs web', place.website || 'Situs web belum tercantum.'],
            ['Telepon', place.phone || 'Nomor telepon belum tersedia.'],
            ['Wikidata', place.wikidata || 'Data Wikidata belum tersedia.'],
            ['Wikipedia', place.wikipedia || 'Halaman Wikipedia belum tersedia.'],
            ['Koordinat', coordinates],
        ];

        if (loading) {
            rows.unshift(['Detail', 'Memuat detail dari OpenStreetMap.']);
        }

        rows.forEach(([label, value]) => {
            const row = document.createElement('div');
            row.className = 'nf-poi-row';

            const key = document.createElement('dt');
            key.className = 'nf-poi-row-label';
            key.textContent = label;

            const detail = document.createElement('dd');
            detail.className = 'nf-poi-row-value';
            detail.textContent = value;

            row.append(key, detail);
            areaPanelBody.append(row);
        });
    }

    function closeAreaPanel() {
        if (!areaPanel || areaPanel.hasAttribute('hidden')) {
            return;
        }

        areaPanel.setAttribute('hidden', '');
        areaPanel.setAttribute('aria-hidden', 'true');

        if (needPanel) {
            needPanel.hidden = false;
            setCardExpanded(cardExpanded);
        }
    }

    async function fetchAreaDetails(place) {
        if (!osmDetailKey(place)) {
            return;
        }

        const seq = ++areaLookupSeq;
        areaLookupController?.abort();
        const controller = new AbortController();
        areaLookupController = controller;
        setAreaPanel(place, { loading: true });

        try {
            const detail = await fetchOsmPlaceDetail(place, { signal: controller.signal });

            if (disposed || seq !== areaLookupSeq || selectedArea !== place || controller !== areaLookupController) {
                return;
            }

            selectedArea = detail ?? place;

            if (areaPreview) {
                areaPreview.geometry?.remove();
                areaPreview.geometry = renderAreaGeometry(selectedArea);
            }

            setAreaPanel(selectedArea);
        } catch (error) {
            if (error?.name === 'AbortError' || disposed || seq !== areaLookupSeq
                || controller !== areaLookupController) {
                return;
            }

            setAreaPanel(place);
        }
    }

    function renderAreaGeometry(place) {
        if (!map || !L) {
            return null;
        }

        const style = {
            weight: 2,
            fillOpacity: 0.16,
            className: 'nf-area-geometry',
        };
        const pairs = countCoordinatePairs(place?.geojson);
        let layer = null;

        if (pairs > 0 && pairs <= AREA_GEOMETRY_MAX_POINTS) {
            try {
                layer = L.geoJSON(place.geojson, {
                    style,
                    pointToLayer: (feature, latlng) => L.circleMarker(latlng, { ...style, radius: 6 }),
                });

                if (layer.getLayers().length === 0) {
                    layer = null;
                }
            } catch {
                layer = null;
            }
        }

        if (!layer && Array.isArray(place?.boundingbox) && place.boundingbox.length === 4) {
            const [south, north, west, east] = place.boundingbox;

            if ([south, north, west, east].every(Number.isFinite) && south <= north && west <= east) {
                layer = L.rectangle([[south, west], [north, east]], style);
            }
        }

        if (!layer) {
            return null;
        }

        layer.on('click', () => {
            if (selectedArea) {
                setAreaPanel(selectedArea);
            }
        });
        layer.addTo(map);

        return layer;
    }

    function createAreaPopup(place) {
        const content = document.createElement('div');
        content.className = 'nf-area-popup max-w-[240px] p-1';
        content.setAttribute('role', 'note');

        const label = document.createElement('p');
        label.className = 'text-[9px] font-semibold uppercase tracking-[0.12em] text-muted';
        label.textContent = `${place.kindLabel} dari OpenStreetMap`;

        const title = document.createElement('p');
        title.className = 'mt-1 text-[12px] font-semibold text-ink';
        title.textContent = place.name;

        const meta = document.createElement('p');
        meta.className = 'mt-0.5 text-[10.5px] leading-snug text-muted';
        meta.textContent = [place.address, place.area].filter(Boolean).join(' · ');

        const notice = document.createElement('p');
        notice.className = 'mt-2 text-[10px] leading-snug text-muted';
        notice.textContent = 'Bukan tempat tersimpan atau pengajuan warga.';

        const saveButton = document.createElement('button');
        saveButton.type = 'button';
        saveButton.dataset.action = 'add-place';
        saveButton.className = 'mt-2 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-50 px-3 text-[11px] font-semibold text-brand-200 transition hover:bg-brand-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40';
        saveButton.setAttribute('aria-label', `Simpan ${place.name} sebagai tempat`);
        saveButton.innerHTML = `${icon('plus', 'size-4')}<span>Jadikan tempat</span>`;

        content.append(label, title, meta, notice, saveButton);

        return L.popup({
            closeButton: true,
            closeOnClick: false,
            autoPan: false,
            maxWidth: 260,
            className: 'nf-area-preview-shell',
        }).setContent(content);
    }

    function scheduleLocationPlaceSearch() {
        window.clearTimeout(locationSearchTimer);
        locationSearchTimer = window.setTimeout(runLocationPlaceSearch, PLACE_SEARCH_DEBOUNCE_MS);
    }

    function runLocationPlaceSearch() {
        if (disposed || !locationPlaceSearch) {
            return;
        }

        const query = locationPlaceSearch.value.trim();
        window.clearTimeout(locationSearchTimer);
        locationSearchController?.abort();

        if (query.length < PLACE_SEARCH_MIN_CHARS) {
            resetPlaceResults(locationResults);
            return;
        }

        const seq = ++locationSearchSeq;
        renderPlaceResults(locationResults, { status: 'loading', hooks: SHEET_RESULT_HOOKS });
        locationSearchController = new AbortController();
        const { signal } = locationSearchController;

        fetchNominatimPlaces(query, { signal, viewbox: currentViewbox() })
            .then((results) => {
                if (disposed || seq !== locationSearchSeq) {
                    return;
                }

                renderPlaceResults(locationResults, { status: 'ready', results, hooks: SHEET_RESULT_HOOKS });
            })
            .catch((error) => {
                if (error?.name === 'AbortError' || disposed || seq !== locationSearchSeq) {
                    return;
                }

                renderPlaceResults(locationResults, { status: 'error', hooks: SHEET_RESULT_HOOKS });
            });
    }

    function openLocationSheet(trigger = null, prefill = null) {
        if (!locationSheet) {
            return;
        }
        closePoiPanel();

        const centerPoint = reticlePoint();

        if (centerPoint) {
            setPickerPoint(locationLatInput, locationLngInput, centerPoint);
        }

        closeFilterSheet();
        locationReturnFocus = trigger instanceof HTMLElement ? trigger : document.activeElement;

        locationSheet.classList.remove('hidden');
        locationSheet.classList.add('flex');

        if (prefill) {
            prefillLocationForm(prefill);
        }

        window.requestAnimationFrame(() => {
            if (disposed) {
                return;
            }

            const created = locationPicker.ensure();
            const lat = Number.parseFloat(locationLatInput?.value ?? '');
            const lng = Number.parseFloat(locationLngInput?.value ?? '');

            if (!created && Number.isFinite(lat) && Number.isFinite(lng)) {
                locationPicker.centerOn(lat, lng);
            }
            locationNameInput?.focus({ preventScroll: true });
        });
    }

    function closeLocationSheet() {
        if (!locationSheet || locationSheet.classList.contains('hidden')) {
            return;
        }

        locationSheet.classList.add('hidden');
        locationSheet.classList.remove('flex');
        window.clearTimeout(locationSearchTimer);
        locationSearchController?.abort();
        resetPlaceResults(locationResults);

        if (locationReturnFocus instanceof HTMLElement) {
            locationReturnFocus.focus({ preventScroll: true });
        }

        locationReturnFocus = null;
    }

    function prefillLocationForm(place) {
        const set = (input, value) => {
            if (input) {
                input.value = value ?? '';
            }
        };

        set(locationNameInput, place.name);
        set(locationAddressInput, place.address);
        set(locationAreaInput, place.area);
        set(locationLatInput, Number.isFinite(place.lat) ? place.lat.toFixed(6) : '');
        set(locationLngInput, Number.isFinite(place.lng) ? place.lng.toFixed(6) : '');

        if (locationCategoryInput && typeof place.category === 'string' && place.category !== ''
            && [...locationCategoryInput.options].some((option) => option.value === place.category)) {
            locationCategoryInput.value = place.category;
        }

        if (Number.isFinite(place.lat) && Number.isFinite(place.lng)
            && locationSheet && !locationSheet.classList.contains('hidden')) {
            const created = locationPicker.ensure();

            if (!created) {
                locationPicker.centerOn(place.lat, place.lng);
            }
        }
    }

    function renderLocationMarkers() {
        if (disposed || !map) {
            return;
        }

        state.locationMarkers.forEach((marker) => marker.remove());
        state.locationMarkers.clear();

        state.locations.forEach((location) => {
            const element = createLocationMarkerElement(location, location.id === state.selectedLocationId);
            const marker = L.marker([location.latitude, location.longitude], {
                icon: L.divIcon({
                    html: element.outerHTML,
                    className: 'nf-leaflet-icon',
                    iconSize: [44, 44],
                    iconAnchor: [15, 44],
                }),
                keyboard: false,
                riseOnHover: true,
            }).addTo(map);

            bindMarkerInteractions(marker, {
                point: [location.longitude, location.latitude],
                label: location.name,
                source: 'Tempat tersimpan',
                onSelect: () => selectLocation(location.id, { pan: false }),
                onKeySelect: () => selectLocation(location.id),
            });

            state.locationMarkers.set(location.id, marker);
        });
        applyMarkerDeclutter();
    }

    function createLocationMarkerElement(location, isSelected) {
        const element = document.createElement('div');
        const categoryId = location.category ?? '';
        element.className = 'nf-location-marker-wrapper nf-map-marker nf-map-marker--saved';
        element.setAttribute('role', 'button');
        element.setAttribute('tabindex', '0');
        element.setAttribute('data-marker-kind', 'saved');
        element.setAttribute('data-source', 'Tempat tersimpan');
        element.setAttribute('data-category', String(categoryId));
        element.setAttribute('data-shape', String(categoryId || 'osm'));
        element.setAttribute('aria-label', `Lokasi ${location.name}${isSelected ? ', dipilih' : ''}`);
        element.style.minWidth = '44px';
        element.style.minHeight = '44px';
        element.style.display = 'flex';
        element.style.alignItems = 'flex-end';
        element.style.justifyContent = 'center';
        element.innerHTML = locationMarkerMarkup(location, isSelected);

        return element;
    }

    function locationMarkerMarkup(location, isSelected) {
        const category = categoryById[location.category] ?? fallbackCategory;
        const markerStyle = `--marker-color:${escapeHtml(category?.color ?? '#6b6256')}`;

        return `<span class="nf-map-marker__pin" style="${markerStyle}">${icon(category.icon ?? 'pin', 'size-3.5')}</span>
                <div class="nf-map-marker__label ${isSelected ? 'is-selected' : ''}" style="${markerStyle}">
                    <span class="nf-map-marker__title">${escapeHtml(location.name)}</span>
                    <span class="nf-map-marker__source">Tempat tersimpan</span>
                </div>`;
    }

    function refreshLocationMarker(locationId) {
        const marker = state.locationMarkers.get(locationId);
        const location = state.locations.find((item) => item.id === locationId);

        if (!marker || !location) {
            return;
        }

        const isSelected = locationId === state.selectedLocationId;
        const iconElement = marker.getElement();
        if (!iconElement) {
            return;
        }
        const element = iconElement?.firstElementChild ?? iconElement;
        element.innerHTML = locationMarkerMarkup(location, isSelected);
        element.setAttribute('aria-label', `Lokasi ${location.name}${isSelected ? ', dipilih' : ''}`);
        iconElement.style.zIndex = isSelected ? '430' : '';
        syncMarkerHtml(marker);
    }

    function selectLocation(locationId, { pan = true } = {}) {
        const location = state.locations.find((item) => item.id === locationId);

        if (!location) {
            return;
        }
        closePoiPanel();
        clearAreaPreview();

        state.selectedLocationId = location.id;
        state.selectedId = null;
        state.markers.forEach((marker, id) => {
            const iconElement = marker.getElement();
            if (!iconElement) {
                return;
            }
            const element = iconElement?.firstElementChild ?? iconElement;
            iconElement.style.zIndex = '';
            const need = state.needs.find((item) => item.id === id);

            if (need) {
                element.innerHTML = markerMarkup(need, false);
            }
            syncMarkerHtml(marker);
        });
        state.locationMarkers.forEach((marker, id) => refreshLocationMarker(id));
        closeLocationSheet();

        setLocationPanel(location);
        syncUrlSelection();

        if (pan && map) {
            panTo(map, toLeafletLatLng([location.longitude, location.latitude]), 0.35);
        }

        announce(`Lokasi ${location.name} dipilih di peta.`);
    }

    function setLocationPanel(location) {
        closePoiPanel();
        if (!locationPanel) {
            return;
        }

        if (!location) {
            locationPanel.setAttribute('hidden', '');
            locationPanel.setAttribute('aria-hidden', 'true');

            if (needPanel) {
                needPanel.hidden = false;
                setCardExpanded(cardExpanded);
            }

            return;
        }

        locationPanel.removeAttribute('hidden');
        locationPanel.setAttribute('aria-hidden', 'false');

        if (locationPanelTitle) {
            locationPanelTitle.textContent = location.name;
        }

        if (locationPanelMeta) {
            const category = categoryById[location.category] ?? fallbackCategory;
            locationPanelMeta.textContent = [category?.label, location.address || location.area]
                .filter(Boolean)
                .join(' · ');
        }
        setLocationPanelBody(location);

        if (needPanel) {
            needPanel.hidden = true;
        }
    }

    // Everything in this body comes from the saved row. No avatar, no invented
    // supporter count, and no placeholder date when the server omitted one.
    function setLocationPanelBody(location) {
        const body = locationPanel?.querySelector('[data-location-panel-body]');

        if (!body) {
            return;
        }

        body.textContent = '';
        const category = categoryById[location.category] ?? fallbackCategory;

        const badge = document.createElement('p');
        badge.className = 'mb-2 inline-flex rounded-lg bg-brand-50 px-2 py-1 text-[9px] font-semibold uppercase tracking-[0.12em] text-brand-200';
        badge.textContent = category?.label ?? 'Tempat';
        body.append(badge);

        const address = document.createElement('p');
        address.className = 'text-[11.5px] leading-relaxed text-muted';
        address.textContent = location.address || 'Alamat belum diisi.';
        body.append(address);

        if (location.area) {
            const area = document.createElement('p');
            area.className = 'mt-1 text-[10.5px] text-muted/80';
            area.textContent = location.area;
            body.append(area);
        }

        const meta = document.createElement('p');
        meta.className = 'mt-3 text-[9.5px] text-muted/70';
        const savedAt = location.created_at ? new Date(location.created_at) : null;
        const dateText = savedAt && !Number.isNaN(savedAt.getTime())
            ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(savedAt)
            : '';
        meta.textContent = `Disimpan warga${dateText ? ` · ${dateText}` : ''}`;
        body.append(meta);
    }

    function closeLocationPanel() {
        state.selectedLocationId = null;

        if (!locationPanel || locationPanel.hasAttribute('hidden')) {
            return;
        }

        state.locationMarkers.forEach((marker, id) => refreshLocationMarker(id));
        setLocationPanel(null);
        syncUrlSelection();
    }

    function refreshMarker(need) {
        const marker = state.markers.get(need.id);

        if (!marker) {
            return;
        }

        const iconElement = marker.getElement();
        if (!iconElement) {
            return;
        }
        const element = iconElement?.firstElementChild ?? iconElement;
        const isSelected = need.id === state.selectedId;
        element.style.minWidth = '44px';
        element.style.minHeight = '44px';
        iconElement.style.zIndex = isSelected ? '420' : '';
        element.innerHTML = markerMarkup(need, isSelected);
        updateMarkerAria(element, need);
        syncMarkerHtml(marker);
    }

    function renderCard({ preserveScroll = false } = {}) {
        const need = state.needs.find((item) => item.id === state.selectedId) ?? null;
        const previousScrollTop = preserveScroll ? needCardElement.scrollTop : 0;

        if (needPeekElement) {
            needPeekElement.innerHTML = need ? needPeekMarkup(need) : emptyPeekMarkup();
        }

        needCardElement.innerHTML = need ? needCardMarkup(need) : emptyCardMarkup();
        needCardElement.scrollTop = previousScrollTop;
    }

    // Solid palette steps in generated cards: no alpha surfaces over the map.
    function needPeekMarkup(need) {
        const category = categoryById[need.category] ?? fallbackCategory;
        const supporters = displaySupporters(need);

        return `
            <span class="flex size-10 shrink-0 items-center justify-center rounded-[14px]" style="background:${category.soft_color};color:${category.color}">
                ${icon(category.icon, 'size-[19px]')}
            </span>

            <div class="min-w-0 flex-1">
                <p class="flex items-center gap-1.5 text-[8px] font-semibold uppercase tracking-[0.12em] text-muted">
                    <span class="truncate">${escapeHtml(category.label)}</span>
                    <span class="text-muted" aria-hidden="true">•</span>
                    <span class="normal-case tracking-normal text-muted">${supporters} dukungan</span>
                </p>
                <h2 class="mt-1 truncate text-[13px] font-semibold tracking-[-0.02em] text-ink">${escapeHtml(need.title)}</h2>
            </div>

            <button
                type="button"
                data-card-toggle
                aria-expanded="${cardExpanded ? 'true' : 'false'}"
                aria-controls="need-card"
                aria-label="${cardExpanded ? 'Tutup detail kebutuhan' : 'Buka detail kebutuhan'}"
                class="inline-flex h-11 shrink-0 items-center gap-1 rounded-full bg-surface px-3 text-[10px] font-semibold text-brand-200 shadow-sm ring-1 ring-white/10 transition hover:bg-surface-2 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20"
            >
                <span data-card-toggle-label>${cardExpanded ? 'Tutup' : 'Detail'}</span>
                ${icon('chevron-down', 'nf-chevron size-3.5 transition-transform duration-200')}
            </button>
        `;
    }

    function emptyPeekMarkup() {
        return `
            <span class="flex size-10 shrink-0 items-center justify-center rounded-[14px] bg-brand-50 text-brand-200">
                ${icon('search', 'size-5')}
            </span>

            <div class="min-w-0 flex-1">
                <p class="text-[8px] font-semibold uppercase tracking-[0.12em] text-brand-300">Peta</p>
                <h2 class="mt-1 truncate text-[13px] font-semibold tracking-[-0.02em] text-ink">Belum ada kebutuhan yang cocok</h2>
            </div>

            <button
                type="button"
                data-card-toggle
                aria-expanded="${cardExpanded ? 'true' : 'false'}"
                aria-controls="need-card"
                aria-label="${cardExpanded ? 'Tutup detail kebutuhan' : 'Buka detail kebutuhan'}"
                class="inline-flex h-11 shrink-0 items-center gap-1 rounded-full bg-surface px-3 text-[10px] font-semibold text-brand-200 shadow-sm ring-1 ring-white/10 transition hover:bg-surface-2 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20"
            >
                <span data-card-toggle-label>${cardExpanded ? 'Tutup' : 'Detail'}</span>
                ${icon('chevron-down', 'nf-chevron size-3.5 transition-transform duration-200')}
            </button>
        `;
    }

    function needCardMarkup(need) {
        const supporters = displaySupporters(need);

        return `
            <article class="need-card-enter">
                <div class="flex items-start gap-1.5 text-[11px] font-medium leading-snug text-muted">
                    ${icon('pin', 'mt-0.5 size-3.5 shrink-0 text-brand-300')}
                    <span>${escapeHtml(need.location)} <span class="text-brand-200">• ${escapeHtml(formatDistance(need.distance_km))}</span></span>
                </div>

                <p class="mt-2 line-clamp-3 text-[12.5px] leading-[1.6] text-muted [@media(max-height:719px)]:line-clamp-2">${escapeHtml(need.description)}</p>

                <div class="mt-3 flex items-center gap-3 rounded-2xl bg-surface-2 px-3 py-2">
                    ${icon('heart', 'size-3.5 shrink-0 fill-[#FB7185] text-[#FB7185]')}
                    <p class="min-w-0 flex-1 text-[11px] font-semibold leading-tight text-ink">
                        <span data-support-count>${supporters}</span> warga mendukung
                    </p>
                    ${need.opportunity ? '<span class="shrink-0 rounded-lg bg-surface px-2 py-1 text-[8.5px] font-semibold text-brand-200">Peluang Usaha</span>' : ''}
                </div>

                <div class="mt-2.5 flex gap-2">
                    <button type="button" data-share-button class="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-50 px-4 text-[12px] font-semibold text-brand-200 transition hover:bg-brand-100 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/25" aria-label="Bagikan ${escapeHtml(need.title)}">
                        ${icon('share', 'size-4 shrink-0')}
                        Bagikan kebutuhan ini
                    </button>
                </div>
            </article>
        `;
    }

    function emptyCardMarkup() {
        return `
            <article class="need-card-enter flex min-h-[260px] flex-col items-center justify-center px-4 py-8 text-center">
                <span class="flex size-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-200">
                    ${icon('search', 'size-7')}
                </span>

                <h2 class="mt-4 text-[17px] font-semibold tracking-[-0.02em] text-ink">Belum ada kebutuhan yang cocok</h2>
                <p class="mt-1.5 max-w-[250px] text-[11.5px] leading-relaxed text-muted">Coba kata kunci lain atau tampilkan kembali semua kategori di sekitar Sukajadi.</p>

                <button type="button" data-reset-filters class="mt-4 inline-flex h-11 items-center justify-center gap-1.5 rounded-xl bg-brand-700 px-4 text-[11px] font-semibold text-canvas shadow-[0_12px_24px_-16px_rgba(0,0,0,0.8)] transition hover:bg-brand-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20">
                    ${icon('map', 'size-3.5')}
                    Tampilkan semua
                </button>
            </article>
        `;
    }

    function fitMapToFiltered() {
        if (!map || state.filtered.length === 0) {
            return;
        }

        const coordinates = state.filtered.map(needPoint).filter(Boolean).map(toLeafletLatLng);
        if (coordinates.length === 0) {
            return;
        }
        const bounds = coordinates.reduce(
            (result, coordinate) => [
                [Math.min(result[0][0], coordinate[0]), Math.min(result[0][1], coordinate[1])],
                [Math.max(result[1][0], coordinate[0]), Math.max(result[1][1], coordinate[1])],
            ],
            [coordinates[0], coordinates[0]],
        );
        const isDesktop = window.matchMedia('(min-width: 1024px)').matches;
        const mapWidth = mapElement.clientWidth || window.innerWidth;
        const mapHeight = mapElement.clientHeight || window.innerHeight;
        const mapCredit = document.querySelector('[data-map-credit]');
        const mapTop = mapElement.getBoundingClientRect().top;
        const creditBottom = mapCredit ? mapCredit.getBoundingClientRect().bottom - mapTop : 0;
        const topPadding = Math.min(
            Math.max(isDesktop ? 180 : 210, creditBottom + 28),
            Math.max(120, mapHeight * 0.34),
        );
        const bottomPadding = Math.min(isDesktop ? 80 : 230, Math.max(60, mapHeight * 0.32));
        const rightPadding = isDesktop ? Math.min(430, Math.max(120, mapWidth * 0.4)) : 80;

        fitBoundsWithPadding(map, bounds, {
            top: topPadding,
            right: rightPadding,
            bottom: bottomPadding,
            left: isDesktop ? 40 : 32,
        }, isDesktop ? 16 : 15.5, 0.35);
    }

    /**
     * Centre the map on one need, keeping it clear of the floating header and the
     * bottom card. Used by a `?need=` deep link so the pin the visitor came for
     * is where the camera lands, not wherever the visitor happens to be.
     */
    function focusNeedOnMap(needId) {
        if (disposed || !map) {
            return;
        }

        const need = state.needs.find((item) => item.id === needId);

        if (!need) {
            return;
        }

        const point = needPoint(need);

        if (!point) {
            return;
        }

        const isDesktop = window.matchMedia('(min-width: 1024px)').matches;
        const mapHeight = mapElement.clientHeight || window.innerHeight;

        setViewWithPadding(map, toLeafletLatLng(point), Math.max(map.getZoom(), 15.5), {
            top: isDesktop ? 180 : 210,
            right: isDesktop ? Math.min(430, Math.max(120, (mapElement.clientWidth || window.innerWidth) * 0.4)) : 80,
            bottom: Math.min(isDesktop ? 80 : 230, Math.max(60, mapHeight * 0.32)),
            left: isDesktop ? 40 : 32,
        });
    }

    function zoomBy(delta) {
        if (disposed || !map) {
            return;
        }

        zoomTo(map, map.getZoom() + delta, 0.2);
    }

    async function shareNeed(needId) {
        const need = state.needs.find((item) => item.id === needId);

        if (!need) {
            return;
        }

        const shareUrl = new URL(window.location.href);
        shareUrl.searchParams.set('need', need.id);

        const shareData = {
            title: `${need.title} - nearFind`,
            text: `Warga di ${need.area} membutuhkan ${need.title}.`,
            url: shareUrl.toString(),
        };

        if (navigator.share) {
            try {
                await navigator.share(shareData);
                return;
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }
            }
        }

        await copyToClipboard(shareData.url);
    }

    async function copyToClipboard(text) {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
                showToast('Tautan kebutuhan disalin.');
                return;
            }

            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();

            const copied = document.execCommand('copy');
            textarea.remove();

            showToast(copied ? 'Tautan kebutuhan disalin.' : 'Tidak dapat menyalin tautan.');
        } catch {
            showToast('Tidak dapat menyalin tautan.');
        }
    }

    function locateUser({ silent = false } = {}) {
        if (disposed || !map) {
            return;
        }

        if (!('geolocation' in navigator)) {
            if (!silent) {
                showToast('Browser ini tidak mendukung fitur lokasi.');
            }

            return;
        }

        const button = document.querySelector('[data-map-locate]');
        button?.setAttribute('aria-busy', 'true');
        button?.classList.add('opacity-60');

        navigator.geolocation.getCurrentPosition(
            ({ coords }) => {
                // The callback can arrive after the tab was left: `disposed` is set
                // by the page teardown and `map` is null by then, so a stale
                // camera move would throw on a detached container.
                if (disposed || !map) {
                    return;
                }

                const location = toLeafletLatLng([coords.longitude, coords.latitude]);
                userPoint = [coords.latitude, coords.longitude];

                if (userMarker) {
                    userMarker.remove();
                }

                const element = document.createElement('div');
                element.className = 'nf-user-marker';
                element.style.pointerEvents = 'none';
                element.innerHTML = '<span class="nf-user-dot"></span>';

                userMarker = L.marker(location, {
                    icon: L.divIcon({
                        html: element.outerHTML,
                        className: 'nf-leaflet-icon nf-user-icon',
                        iconSize: [18, 18],
                        iconAnchor: [9, 9],
                    }),
                    keyboard: false,
                    riseOnHover: false,
                }).addTo(map);

                map.flyTo(location, 16, { animate: true, duration: 0.6 });

                if (!silent) {
                    showToast('Lokasi kamu ditemukan.');
                }

                button?.removeAttribute('aria-busy');
                button?.classList.remove('opacity-60');
            },
            () => {
                if (disposed || !map) {
                    return;
                }

                if (!silent) {
                    showToast('Lokasi tidak dapat diakses. Izinkan akses lokasi di browser.');
                }

                button?.removeAttribute('aria-busy');
                button?.classList.remove('opacity-60');
            },
            {
                enableHighAccuracy: true,
                timeout: 8000,
                maximumAge: 60000,
            },
        );
    }

    function syncUrlSelection() {
        const url = new URL(window.location.href);

        if (state.selectedLocationId) {
            url.searchParams.set('location', state.selectedLocationId);
            url.searchParams.delete('need');
        } else if (state.selectedId) {
            url.searchParams.set('need', state.selectedId);
            url.searchParams.delete('location');
        } else {
            url.searchParams.delete('need');
            url.searchParams.delete('location');
        }

        window.history.replaceState({}, '', url);
    }

    function showToast(message) {
        if (!toast || !toastMessage) {
            return;
        }

        toastMessage.textContent = message;
        toast.classList.add('is-visible');

        window.clearTimeout(toastTimeout);
        toastTimeout = window.setTimeout(() => {
            toast.classList.remove('is-visible');
        }, 3400);
    }

    function displaySupporters(need) {
        return Number(need.supporters) || 0;
    }

    function formatDistance(distanceKm) {
        return `${Number(distanceKm).toFixed(1).replace('.', ',')} km`;
    }

    return () => {
        disposed = true;

        try {
            controller.abort();
        } catch {
            // An already-aborted controller is harmless.
        }

        window.clearTimeout(toastTimeout);
        window.clearTimeout(placeSearchTimer);
        window.clearTimeout(locationSearchTimer);
        placeSearchController?.abort();
        locationSearchController?.abort();

        window.clearTimeout(poiTimer);
        window.clearTimeout(poiReverseTimer);
        poiController?.abort();
        poiReverseController?.abort();
        poiMarkers.forEach((marker) => marker.remove());
        poiMarkers.clear();
        poiCache.clear();
        reverseCache.clear();
        currentPois = [];
        selectedPoiId = null;
        if (poiPanel) {
            poiPanel.setAttribute('hidden', '');
            poiPanel.setAttribute('aria-hidden', 'true');
        }

        needPicker.destroy();
        locationPicker.destroy();

        tooltip.remove();
        clearAreaPreview();
        state.markers.forEach((marker) => marker.remove());
        state.locationMarkers.forEach((marker) => marker.remove());
        userMarker?.remove();
        resizeObserver?.disconnect();

        if (map) {
            try {
                map.remove();
            } catch {
                // The container may already be detached by the swap.
            }

            map = null;
        }

        userMarker = null;
        state.markers.clear();
        state.locationMarkers.clear();
    };
}
