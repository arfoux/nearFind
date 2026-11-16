// MapLibre is only needed by the map page. Loading it on first paint of every
// other tab costs ~1 MB of JS, so the module is fetched on first map use.
// The classes are assigned before the promise resolves, so every existing
// `new MapLibreMap` / `new Marker` / `new Popup` call site is unchanged.
let MapLibreMap;
let Marker;
let Popup;

let mapLibrePromise = null;

function ensureMapLibre() {
    if (!mapLibrePromise) {
        mapLibrePromise = Promise.all([
            import('maplibre-gl'),
            import('maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url'),
        ]).then(([mod, worker]) => {
            MapLibreMap = mod.Map;
            Marker = mod.Marker;
            Popup = mod.Popup;
            mod.setWorkerUrl(worker.default);
        });
    }
    return mapLibrePromise;
}

// Design read, kept next to the code it drives: a civic map tool for warga,
// utilitarian warm-neutral language, one violet signal accent. ENERGY 2 /
// RHYTHM 2 / MOTION 1 because a map utility must stay calm, low-motion and
// legible on a phone. The camera is the only thing that moves on purpose.
const NOMINATIM_SEARCH_ENDPOINT = 'https://nominatim.openstreetmap.org/search';
const NOMINATIM_RESULT_LIMIT = 5;
const PLACE_SEARCH_DEBOUNCE_MS = 350;
const PLACE_SEARCH_MIN_CHARS = 3;

const THEME_STORAGE_KEY = 'nf-theme';
const THEME_DARK = 'dark';
const THEME_LIGHT = 'light';
const THEME_META_COLOR = { [THEME_DARK]: '#0B0E12', [THEME_LIGHT]: '#F7F5F1' };
const FALLBACK_DARK_STYLE = 'https://tiles.openfreemap.org/styles/dark';
const FALLBACK_LIGHT_STYLE = 'https://tiles.openfreemap.org/styles/light';

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

// This reads the same key and the same OS signal as the layout's pre-paint
// script. If the two ever disagree, the first paint flashes the wrong theme.
function resolveTheme() {
    let stored = null;
    try {
        stored = window.localStorage.getItem(THEME_STORAGE_KEY);
    } catch {
        // Storage can be blocked; the OS preference is still a real signal.
    }

    if (stored === THEME_DARK || stored === THEME_LIGHT) {
        return stored;
    }

    return window.matchMedia('(prefers-color-scheme: light)').matches ? THEME_LIGHT : THEME_DARK;
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

function normalizePlaceResult(item) {
    const lat = Number(item?.lat);
    const lng = Number(item?.lon);

    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        return null;
    }

    const displayName = String(item?.display_name ?? '');
    const name = String(item?.name ?? '').trim() || displayName.split(',')[0].trim();

    if (!name) {
        return null;
    }

    return { name, address: displayName, area: areaFromAddress(item?.address ?? {}), lat, lng };
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

async function fetchNominatimPlaces(query, { signal } = {}) {
    const url = new URL(NOMINATIM_SEARCH_ENDPOINT);
    url.searchParams.set('format', 'jsonv2');
    url.searchParams.set('q', query);
    url.searchParams.set('limit', String(NOMINATIM_RESULT_LIMIT));
    url.searchParams.set('addressdetails', '1');

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
    setChildHidden(root, hooks.empty, status === 'ready' && results.length === 0);
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
        button.dataset.name = place.name;
        button.dataset.address = place.address;
        button.dataset.area = place.area;
        button.dataset.lat = String(place.lat);
        button.dataset.lng = String(place.lng);
        button.className = 'flex min-h-[44px] w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40';
        button.setAttribute('aria-label', `Pilih ${place.name}`);
        button.innerHTML = `
            <span class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-200">${icon('pin', 'size-4')}</span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-[12px] font-semibold text-ink">${escapeHtml(place.name)}</span>
                <span class="block truncate text-[10.5px] leading-snug text-muted">${escapeHtml(place.address)}</span>
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

        handler({
            name: button.dataset.name ?? '',
            address: button.dataset.address ?? '',
            area: button.dataset.area ?? '',
            lat: Number(button.dataset.lat),
            lng: Number(button.dataset.lng),
        });
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
            map.jumpTo({ center: [lng, lat], ...(Number.isFinite(zoom) ? { zoom } : {}) });
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

    function nudge(direction) {
        if (!map) {
            return;
        }

        const step = direction === 'west' ? [-0.0015, 0]
            : direction === 'east' ? [0.0015, 0]
                : direction === 'north' ? [0, 0.0015] : [0, -0.0015];
        const center = map.getCenter();
        map.easeTo({ center: [center.lng + step[0], center.lat + step[1]], duration: 160 });
    }

    function zoomBy(delta) {
        if (map) {
            map.easeTo({ zoom: map.getZoom() + delta, duration: 180 });
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
            map = new MapLibreMap({
                container: canvas,
                style: styleUrl(),
                center: start,
                zoom: 16,
                minZoom: 12,
                maxZoom: 18,
                attributionControl: false,
                dragRotate: false,
                pitchWithRotate: false,
                maxPitch: 0,
                renderWorldCopies: false,
            });
        } catch (error) {
            console.error('nearFind: pemilih lokasi gagal dimuat.', error);
            map = null;
            return false;
        }

        map.on('load', () => map?.resize());
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
            map?.setStyle(url);
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

const DEFAULT_MAP_STYLE_URL = 'https://tiles.openfreemap.org/styles/dark';

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
        await ensureMapLibre();
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

    // Dispose BEFORE the old markup goes: MapLibre's remove() needs the container
    // it was given, and emptying the slot first left it calling
    // getCanvasContainer() on a detached node ("Cannot read properties of null").
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

// The nav is rendered once by the server, so its "current tab" styling has to be
// repainted by the client on every swap: without this the highlight keeps
// pointing at whichever tab the document was first served for.
const NAV_ICON_ACTIVE = 'bg-brand-700 text-canvas shadow-[0_8px_18px_-12px_rgba(0,0,0,0.8)]';
const NAV_ICON_IDLE = 'text-muted group-hover:bg-brand-50 group-hover:text-brand-200';
const NAV_LABEL_ACTIVE = 'text-brand-200';
const NAV_LABEL_IDLE = 'text-muted group-hover:text-brand-200';

function syncNav(tab) {
    document.querySelectorAll('[data-nav-link]').forEach((link) => {
        const isActive = link.dataset.navKey === tab;

        if (isActive) {
            link.setAttribute('aria-current', 'page');
        } else {
            link.removeAttribute('aria-current');
        }

        const icon = link.querySelector('[data-nav-icon]');
        const label = link.querySelector('[data-nav-label]');

        if (icon) {
            icon.classList.remove(...(isActive ? NAV_ICON_IDLE : NAV_ICON_ACTIVE).split(' ').filter(Boolean));
            icon.classList.add(...(isActive ? NAV_ICON_ACTIVE : NAV_ICON_IDLE).split(' ').filter(Boolean));
        }

        if (label) {
            label.classList.remove(...(isActive ? NAV_LABEL_IDLE : NAV_LABEL_ACTIVE).split(' ').filter(Boolean));
            label.classList.add(...(isActive ? NAV_LABEL_ACTIVE : NAV_LABEL_IDLE).split(' ').filter(Boolean));
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
    { categories, needs, locations = [], authenticated = false, mapStyleUrl = DEFAULT_MAP_STYLE_URL, mapDarkStyleUrl, mapLightStyleUrl, flash = null },
    { mapElement, needCardElement },
) {
    // A deployment may advertise two styles; a legacy deployment only has one.
    // Either way the map starts in the resolved theme and a press swaps styles.
    themeStyles.dark = mapDarkStyleUrl || mapStyleUrl || FALLBACK_DARK_STYLE;
    themeStyles.light = mapLightStyleUrl || mapStyleUrl || FALLBACK_LIGHT_STYLE;

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
        sort: 'relevance',
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
    const locationPanel = document.querySelector('[data-location-panel]');
    const locationPanelTitle = locationPanel?.querySelector('[data-location-panel-title]');
    const locationPanelMeta = locationPanel?.querySelector('[data-location-panel-meta]');
    const locationPanelClose = locationPanel?.querySelector('[data-location-panel-close]');
    const toast = document.getElementById('toast');
    const toastMessage = toast?.querySelector('[data-toast-message]');
    const mapTip = document.querySelector('[data-map-tip]');
    const needPanel = document.getElementById('need-panel');
    const needPeekElement = document.getElementById('need-peek');
    const mapControls = document.querySelector('[data-map-controls]');
    const desktopQuery = window.matchMedia('(min-width: 1024px)');
    const params = new URLSearchParams(window.location.search);
    const requestedNeedId = params.get('need');
    const requestedLocationId = params.get('location');

    const initialNeed = needs.find((need) => need.id === requestedNeedId) ?? needs[0] ?? null;
    state.selectedId = initialNeed?.id ?? null;
    let cardExpanded = desktopQuery.matches;

    const tooltip = new Popup({
        closeButton: false,
        closeOnClick: false,
        focusAfterOpen: false,
        anchor: 'bottom',
        offset: [0, -50],
        maxWidth: '260px',
        className: 'nf-tooltip',
    });

    let map = null;
    let userMarker = null;
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
    const controller = new AbortController();
    const listenerOptions = { signal: controller.signal };

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

        map?.resize();

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

    function initMap() {
        try {
            map = new MapLibreMap({
                container: mapElement,
                style: themeStyles[currentTheme()],
                center: [107.5944, -6.8903],
                zoom: 15,
                minZoom: 13,
                maxZoom: 19,
                attributionControl: false,
                dragRotate: false,
                pitchWithRotate: false,
                maxPitch: 0,
                renderWorldCopies: false,
            });
        } catch (error) {
            console.error('nearFind: peta gagal dibuat.', error);
            showToast('Peta tidak dapat dimuat di browser ini.');
            return;
        }

        map.setZoomSnap(0.25);
        map.touchZoomRotate.disableRotation();
        map.keyboard.disableRotation();

        map.on('error', (event) => {
            if (!event?.error) {
                return;
            }

            mapErrors += 1;

            if (mapErrors >= 4 && !mapErrorShown) {
                mapErrorShown = true;
                showToast('Peta tidak dapat memuat tile. Periksa koneksi internetmu.');
            }
        });

        if ('ResizeObserver' in window) {
            resizeObserver = new ResizeObserver(() => map?.resize());
            resizeObserver.observe(mapElement);
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

        document.querySelectorAll('[data-category]').forEach((button) => {
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
                    openLocationSheet(actionElement);
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
        state.sort = 'relevance';
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
        const sortedNeeds = [...filteredNeeds];

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
                sortedNeeds.sort((first, second) => first.distance_km - second.distance_km);
                break;
            default:
                break;
        }

        return sortedNeeds;
    }

    function syncChips() {
        document.querySelectorAll('[data-category]').forEach((button) => {
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

        closeFilterSheet();
        submitReturnFocus = trigger instanceof HTMLElement ? trigger : document.activeElement;

        submitSheet.classList.remove('hidden');
        submitSheet.classList.add('flex');

        requestAnimationFrame(() => {
            submitSheet.querySelector('#need-title')?.focus({ preventScroll: true });
        });

        // The map inside the sheet cannot be laid out while it is `display:none`,
        // so it is only created once the sheet is actually on screen.
        needPicker.ensure();
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
            map?.setStyle(styleUrl);
        } catch {
            // A style swap during a camera transition is harmless.
        }
        needPicker.setStyle(styleUrl);
        locationPicker.setStyle(styleUrl);
    }, listenerOptions);

    function putPointOnMap(lat, lng) {
        if (pointLat) {
            pointLat.value = lat.toFixed(6);
        }

        if (pointLng) {
            pointLng.value = lng.toFixed(6);
        }

        pointHint?.classList.add('hidden');

        if (pointSummary) {
            pointSummary.textContent = `${lat.toFixed(5)}, ${lng.toFixed(5)} · pin tepat di lokasi kebutuhan`;
        }
    }

    /** Move the map so the fixed pin lands on a given point (locate, restore). */
    function centerPointMap(lat, lng) {
        if (!pointMap) {
            return;
        }

        pointSyncing = true;
        pointMap.jumpTo({ center: [lng, lat] });
        pointSyncing = false;
    }

    /** The pin is the map centre at the end of any interaction. */
    function commitPoint() {
        if (!pointMap || pointSyncing) {
            return;
        }

        const center = pointMap.getCenter();
        putPointOnMap(center.lat, center.lng);
        fetchAreaName(center.lat, center.lng);
    }

    /**
     * Reverse geocode through the public Nominatim endpoint so "area" is a real
     * place name instead of whatever the visitor typed. Bounded and debounced: a
     * pin being nudged must not fire a request per pixel, and a failed lookup
     * must never block the form (the field stays editable).
     */
    function fetchAreaName(lat, lng) {
        if (!areaInput || areaInput.value.trim() !== '') {
            return;
        }

        window.clearTimeout(pointAreaTimer);
        pointAreaTimer = window.setTimeout(async () => {
            if (disposed) {
                return;
            }

            const request = ++pointAreaRequest;
            pointBusy?.classList.remove('hidden');
            pointBusy?.classList.add('flex');

            try {
                const response = await fetch(
                    `https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=16&lat=${lat}&lon=${lng}`,
                    { headers: { Accept: 'application/json' }, signal: controller.signal },
                );

                if (!response.ok || request !== pointAreaRequest || disposed) {
                    return;
                }

                const data = await response.json();
                const address = data?.address ?? {};
                const parts = [
                    address.village ?? address.suburb ?? address.neighbourhood ?? address.quarter,
                    address.city_district ?? address.city ?? address.town ?? address.county,
                ].filter(Boolean);

                if (parts.length > 0 && areaInput && areaInput.value.trim() === '') {
                    areaInput.value = parts.join(', ');
                }
            } catch {
                // Offline, rate limited or blocked: the visitor can still type it.
            } finally {
                if (request === pointAreaRequest) {
                    pointBusy?.classList.add('hidden');
                    pointBusy?.classList.remove('flex');
                }
            }
        }, 420);
    }

    function nudgePoint(direction) {
        if (!pointMap) {
            return;
        }

        const step = direction === 'west' ? [-0.0015, 0] : direction === 'east' ? [0.0015, 0]
            : direction === 'north' ? [0, 0.0015] : [0, -0.0015];
        const center = pointMap.getCenter();

        pointMap.easeTo({ center: [center.lng + step[0], center.lat + step[1]], duration: 160 });
    }

    function locatePoint() {
        if (!('geolocation' in navigator)) {
            showToast('Browser ini tidak mendukung fitur lokasi.');

            return;
        }

        navigator.geolocation.getCurrentPosition(
            ({ coords }) => {
                if (disposed || !pointMap) {
                    return;
                }

                if (areaInput) {
                    areaInput.value = '';
                }

                pointMap.jumpTo({ center: [coords.longitude, coords.latitude], zoom: 17 });
                putPointOnMap(coords.latitude, coords.longitude);
                fetchAreaName(coords.latitude, coords.longitude);
            },
            () => {
                showToast('Lokasi kamu tidak bisa dibaca. Geser peta saja.');
            },
            { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 },
        );
    }

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
            const marker = new Marker({
                element,
                anchor: 'bottom',
                subpixelPositioning: true,
            })
                .setLngLat(point)
                .addTo(map);

            marker.on('click', () => selectNeed(need.id));

            element.addEventListener('mouseenter', () => showMarkerTooltip(need));
            element.addEventListener('focus', () => showMarkerTooltip(need));
            element.addEventListener('mouseleave', hideMarkerTooltip);
            element.addEventListener('blur', hideMarkerTooltip);
            element.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }

                event.preventDefault();
                selectNeed(need.id);
            });

            state.markers.set(need.id, marker);
        });

        renderLocationMarkers();
    }

    function createMarkerElement(need, isSelected) {
        const element = document.createElement('div');
        element.className = 'nf-marker-wrapper';
        element.setAttribute('role', 'button');
        element.setAttribute('tabindex', '0');
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

        return `<div class="nf-marker ${isSelected ? 'is-selected' : ''}" style="${markerStyle}">
                    <div class="nf-marker-pill">
                        <span class="nf-marker-pill-icon">${icon(category.icon, 'size-3.5')}</span>
                        <span class="nf-marker-pill-title">${escapeHtml(need.marker_label)}</span>
                        <span class="nf-marker-pill-count">${supporters}</span>
                    </div>
                    <span class="nf-marker-tail"></span>
                </div>`;
    }

    function updateMarkerAria(element, need) {
        const category = categoryById[need.category] ?? fallbackCategory;
        element.setAttribute(
            'aria-label',
            `${need.title}, kategori ${category.label}, ${displaySupporters(need)} warga mendukung`,
        );
    }

    function showMarkerTooltip(need) {
        const point = needPoint(need);

        if (disposed || !map || !point) {
            return;
        }

        tooltip
            .setLngLat(point)
            .setText(`${need.marker_label} · ${displaySupporters(need)} warga`)
            .addTo(map);
    }

    function hideMarkerTooltip() {
        tooltip.remove();
    }

    function selectNeed(needId, { pan = true, preserveScroll = false } = {}) {
        const need = state.needs.find((item) => item.id === needId);

        if (!need) {
            return;
        }

        const point = needPoint(need);

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
                map.panTo(point, { duration: 350 });
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

        if (map) {
            map.panTo([place.lng, place.lat], { duration: 350 });
        }

        const saved = findSavedLocation(place);

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

        fetchNominatimPlaces(query, { signal })
            .then((results) => {
                if (disposed || seq !== placeSearchSeq || searchInput.value.trim() !== query) {
                    return;
                }

                topAreaResults = results;
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

    function appendSearchGroup(list, label, buttons) {
        if (buttons.length === 0) {
            return;
        }

        const heading = document.createElement('li');
        heading.setAttribute('role', 'presentation');
        heading.className = 'px-3 pb-1 pt-2 text-[9px] font-semibold uppercase tracking-[0.12em] text-muted';
        heading.textContent = label;
        list.append(heading);

        buttons.forEach((button) => {
            const row = document.createElement('li');
            row.append(button);
            list.append(row);
        });
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
        const places = state.locations
            .filter((location) => matchesWords([location.name, location.address, location.area].join(' '), words))
            .slice(0, NOMINATIM_RESULT_LIMIT);
        const needs = state.needs
            .filter((need) => matchesWords(
                [need.title, need.marker_label, need.location, need.area, need.description].join(' '),
                words,
            ))
            .slice(0, NOMINATIM_RESULT_LIMIT);
        const areas = topAreaQuery === query ? topAreaResults : [];

        const placeButtons = places.map((location) => {
            const category = categoryById[location.category] ?? fallbackCategory;
            return makeSearchResultButton({
                kind: 'location',
                title: location.name,
                meta: [category?.label, location.address || location.area].filter(Boolean).join(' · '),
                iconName: 'pin',
                iconClass: 'bg-brand-50 text-brand-200',
                ariaLabel: `Pilih tempat tersimpan ${location.name}`,
                dataset: { locationId: location.id },
            });
        });
        const needButtons = needs.map((need) => {
            const category = categoryById[need.category] ?? fallbackCategory;
            const point = needPoint(need);
            return makeSearchResultButton({
                kind: 'need',
                title: need.title,
                meta: point ? `${need.location} · ${category?.label ?? 'Kebutuhan'}` : `${need.location} · belum ada titik peta`,
                iconName: 'clipboard',
                iconClass: '',
                iconStyle: `background:${category?.soft_color ?? 'var(--color-brand-50)'};color:${category?.color ?? 'var(--color-brand-200)'}`,
                ariaLabel: `Pilih pengajuan warga ${need.title}`,
                dataset: { needId: need.id },
            });
        });
        const areaButtons = areas.map((area) => makeSearchResultButton({
            kind: 'area',
            title: area.name,
            meta: `Dari OpenStreetMap · ${area.address}`,
            iconName: 'map',
            iconClass: 'bg-canvas text-muted',
            ariaLabel: `Pilih area ${area.name} dari peta`,
            dataset: { name: area.name, address: area.address, area: area.area, lat: String(area.lat), lng: String(area.lng) },
        }));

        const list = placeTopResults.querySelector(TOP_RESULT_HOOKS.list);
        if (list) {
            list.textContent = '';
            appendSearchGroup(list, 'Tempat tersimpan', placeButtons);
            appendSearchGroup(list, 'Pengajuan warga', needButtons);
            appendSearchGroup(list, 'Area', areaButtons);
        }

        const total = placeButtons.length + needButtons.length + areaButtons.length;
        const isPending = query.length >= PLACE_SEARCH_MIN_CHARS && status === 'loading';
        setChildHidden(placeTopResults, TOP_RESULT_HOOKS.loading, !isPending);
        setChildHidden(placeTopResults, TOP_RESULT_HOOKS.error, status !== 'error');
        setChildHidden(placeTopResults, TOP_RESULT_HOOKS.empty, status === 'ready' && total === 0);
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

        onTopPlaceSelected({
            name: button.dataset.name,
            address: button.dataset.address,
            area: button.dataset.area,
            lat: Number(button.dataset.lat),
            lng: Number(button.dataset.lng),
        });
    }

    function clearAreaPreview() {
        if (!areaPreview) {
            return;
        }

        areaPreview.popup.remove();
        areaPreview.marker.remove();
        areaPreview = null;
    }

    function showAreaPreview(place) {
        if (disposed || !map) {
            return;
        }

        clearAreaPreview();

        const element = document.createElement('div');
        element.className = 'nf-area-marker-wrapper';
        element.setAttribute('role', 'button');
        element.setAttribute('tabindex', '0');
        element.setAttribute('aria-label', `Area ${place.name} di peta`);
        element.style.minWidth = '44px';
        element.style.minHeight = '44px';
        element.innerHTML = `<span class="nf-area-marker">${icon('map', 'size-4')}</span>`;

        const marker = new Marker({ element, anchor: 'bottom', subpixelPositioning: true })
            .setLngLat([place.lng, place.lat])
            .addTo(map);
        const popup = createAreaPopup(place)
            .setLngLat([place.lng, place.lat])
            .addTo(map);

        element.addEventListener('click', () => popup.addTo(map));
        element.addEventListener('keydown', (searchEvent) => {
            if (searchEvent.key !== 'Enter' && searchEvent.key !== ' ') {
                return;
            }

            searchEvent.preventDefault();
            popup.addTo(map);
        });

        areaPreview = { marker, popup };
    }

    function createAreaPopup(place) {
        const content = document.createElement('div');
        content.className = 'nf-area-popup max-w-[240px] p-1';
        content.setAttribute('role', 'note');

        const label = document.createElement('p');
        label.className = 'text-[9px] font-semibold uppercase tracking-[0.12em] text-muted';
        label.textContent = 'Area dari OpenStreetMap';

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
        saveButton.className = 'mt-2 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-50 px-3 text-[11px] font-semibold text-brand-200 transition hover:bg-brand-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40';
        saveButton.setAttribute('aria-label', `Simpan ${place.name} sebagai tempat`);
        saveButton.innerHTML = `${icon('plus', 'size-4')}<span>Jadikan tempat</span>`;
        saveButton.addEventListener('click', () => openLocationSheet(saveButton, place));

        content.append(label, title, meta, notice, saveButton);

        return new Popup({
            closeButton: true,
            closeOnClick: false,
            focusAfterOpen: false,
            maxWidth: '260px',
            className: 'nf-area-preview-shell',
        }).setDOMContent(content);
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

        fetchNominatimPlaces(query, { signal })
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

        closeFilterSheet();
        locationReturnFocus = trigger instanceof HTMLElement ? trigger : document.activeElement;

        if (prefill) {
            prefillLocationForm(prefill);
        }

        locationSheet.classList.remove('hidden');
        locationSheet.classList.add('flex');

        window.requestAnimationFrame(() => {
            if (disposed) {
                return;
            }

            locationPicker.ensure();
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
            const marker = new Marker({ element, anchor: 'bottom', subpixelPositioning: true })
                .setLngLat([location.longitude, location.latitude])
                .addTo(map);
            const popup = createLocationPopup(location);

            marker.on('click', () => selectLocation(location.id, { pan: false }));
            element.addEventListener('mouseenter', () => popup.addTo(map));
            element.addEventListener('focus', () => popup.addTo(map));
            element.addEventListener('mouseleave', () => popup.remove());
            element.addEventListener('blur', () => popup.remove());
            element.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }

                event.preventDefault();
                selectLocation(location.id);
            });

            state.locationMarkers.set(location.id, marker);
        });
    }

    function createLocationMarkerElement(location, isSelected) {
        const element = document.createElement('div');
        element.className = 'nf-location-marker-wrapper';
        element.setAttribute('role', 'button');
        element.setAttribute('tabindex', '0');
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

        return `<div class="nf-location-marker ${isSelected ? 'is-selected' : ''}" style="--marker-color:${escapeHtml(category?.color ?? '#7C3AED')}">
                    <span class="nf-location-marker-icon">${icon('pin', 'size-3.5')}</span>
                    <span class="nf-location-marker-name">${escapeHtml(location.name)}</span>
                </div>`;
    }

    function createLocationPopup(location) {
        const content = document.createElement('div');
        content.className = 'nf-location-popup max-w-[240px] p-1';
        content.setAttribute('role', 'note');

        const title = document.createElement('p');
        title.className = 'text-[12px] font-semibold text-ink';
        title.textContent = location.name;

        const meta = document.createElement('p');
        meta.className = 'mt-0.5 text-[10.5px] leading-snug text-muted';
        const category = categoryById[location.category] ?? fallbackCategory;
        meta.textContent = [category?.label, location.address || location.area].filter(Boolean).join(' · ');

        content.append(title, meta);

        return new Popup({
            closeButton: false,
            closeOnClick: false,
            focusAfterOpen: false,
            offset: 12,
            maxWidth: '260px',
            className: 'nf-location-popup-shell',
        }).setDOMContent(content);
    }

    function refreshLocationMarker(locationId) {
        const marker = state.locationMarkers.get(locationId);
        const location = state.locations.find((item) => item.id === locationId);

        if (!marker || !location) {
            return;
        }

        const isSelected = locationId === state.selectedLocationId;
        const element = marker.getElement();
        element.innerHTML = locationMarkerMarkup(location, isSelected);
        element.style.zIndex = isSelected ? '430' : '';
        element.setAttribute('aria-label', `Lokasi ${location.name}${isSelected ? ', dipilih' : ''}`);
    }

    function selectLocation(locationId, { pan = true } = {}) {
        const location = state.locations.find((item) => item.id === locationId);

        if (!location) {
            return;
        }
        clearAreaPreview();

        state.selectedLocationId = location.id;
        state.selectedId = null;
        state.markers.forEach((marker, id) => {
            const element = marker.getElement();
            element.style.zIndex = '';
            const need = state.needs.find((item) => item.id === id);

            if (need) {
                element.innerHTML = markerMarkup(need, false);
            }
        });
        state.locationMarkers.forEach((marker, id) => refreshLocationMarker(id));
        closeLocationSheet();

        setLocationPanel(location);
        syncUrlSelection();

        if (pan && map) {
            map.panTo([location.longitude, location.latitude], { duration: 350 });
        }

        announce(`Lokasi ${location.name} dipilih di peta.`);
    }

    function setLocationPanel(location) {
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

        const element = marker.getElement();
        const isSelected = need.id === state.selectedId;
        element.style.minWidth = '44px';
        element.style.minHeight = '44px';
        element.style.zIndex = isSelected ? '420' : '';
        element.innerHTML = markerMarkup(need, isSelected);
        updateMarkerAria(element, need);
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
                    <span class="text-muted/60" aria-hidden="true">•</span>
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
                class="inline-flex h-11 shrink-0 items-center gap-1 rounded-full bg-surface/80 px-3 text-[10px] font-semibold text-brand-200 shadow-sm ring-1 ring-white/10 transition hover:bg-surface-2 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20"
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
                class="inline-flex h-11 shrink-0 items-center gap-1 rounded-full bg-surface/80 px-3 text-[10px] font-semibold text-brand-200 shadow-sm ring-1 ring-white/10 transition hover:bg-surface-2 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20"
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

                <div class="mt-3 flex items-center gap-3 rounded-2xl bg-white/5 px-3 py-2">
                    ${icon('heart', 'size-3.5 shrink-0 fill-[#FB7185] text-[#FB7185]')}
                    <p class="min-w-0 flex-1 text-[11px] font-semibold leading-tight text-ink">
                        <span data-support-count>${supporters}</span> warga mendukung
                    </p>
                    ${need.opportunity ? '<span class="shrink-0 rounded-lg bg-surface/80 px-2 py-1 text-[8.5px] font-semibold text-brand-200">Peluang Usaha</span>' : ''}
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

        const coordinates = state.filtered.map(needPoint).filter(Boolean);
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

        map.fitBounds(bounds, {
            padding: {
                top: topPadding,
                right: rightPadding,
                bottom: bottomPadding,
                left: isDesktop ? 40 : 32,
            },
            maxZoom: isDesktop ? 16 : 15.5,
            linear: true,
            duration: 350,
        });
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

        const point = needPoint(need);

        if (!point) {
            return;
        }

        const isDesktop = window.matchMedia('(min-width: 1024px)').matches;
        const mapHeight = mapElement.clientHeight || window.innerHeight;

        map.jumpTo({
            center: point,
            zoom: Math.max(map.getZoom(), 15.5),
            padding: {
                top: isDesktop ? 180 : 210,
                right: isDesktop ? Math.min(430, Math.max(120, (mapElement.clientWidth || window.innerWidth) * 0.4)) : 80,
                bottom: Math.min(isDesktop ? 80 : 230, Math.max(60, mapHeight * 0.32)),
                left: isDesktop ? 40 : 32,
            },
        });
    }

    function zoomBy(delta) {
        if (disposed || !map) {
            return;
        }

        map.stop();
        map.easeTo({ zoom: map.getZoom() + delta, duration: 200 });
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
                // by the page teardown and `map` is null by then, so anything that
                // touches MapLibre here would throw "getCanvasContainer" on a
                // detached container.
                if (disposed || !map) {
                    return;
                }

                const location = [coords.longitude, coords.latitude];

                if (userMarker) {
                    userMarker.remove();
                }

                const element = document.createElement('div');
                element.className = 'nf-user-marker';
                element.style.pointerEvents = 'none';
                element.innerHTML = '<span class="nf-user-dot"></span>';

                userMarker = new Marker({
                    element,
                    anchor: 'center',
                })
                    .setLngLat(location)
                    .addTo(map);

                map.flyTo({ center: location, zoom: 16, duration: 600 });

                if (!silent) {
                    showToast('Lokasi kamu ditemukan.');
                }

                button?.removeAttribute('aria-busy');
                button?.classList.remove('opacity-60');
            },
            () => {
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

        resizeObserver?.disconnect();
        tooltip.remove();
        window.clearTimeout(toastTimeout);
        window.clearTimeout(placeSearchTimer);
        window.clearTimeout(locationSearchTimer);
        placeSearchController?.abort();
        locationSearchController?.abort();
        needPicker.destroy();
        locationPicker.destroy();

        if (map) {
            try {
                map.remove();
            } catch {
                // The container may already be detached by the swap.
            }

            map = null;
        }

        userMarker = null;
        state.locationMarkers.clear();
    };
}
