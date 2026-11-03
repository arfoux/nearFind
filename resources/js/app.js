import { Map as MapLibreMap, Marker, Popup, setWorkerUrl } from 'maplibre-gl';
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';

setWorkerUrl(workerUrl);

const DEFAULT_MAP_STYLE_URL = 'https://tiles.openfreemap.org/styles/positron';

const dataElement = document.getElementById('nearfind-data');
const needCardElement = document.getElementById('need-card');
const mapElement = document.getElementById('map');

if (dataElement && needCardElement && mapElement) {
    try {
        initNearFind(JSON.parse(dataElement.textContent));
    } catch (error) {
        console.error('nearFind: gagal menginisialisasi halaman.', error);
    }
}

initPasswordToggles();

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

function initNearFind({ categories, needs, authenticated = false, mapStyleUrl = DEFAULT_MAP_STYLE_URL }) {
    const categoryById = Object.fromEntries(categories.map((category) => [category.id, category]));
    const fallbackCategory = categoryById.all ?? categories[0];

    const state = {
        categories,
        needs,
        category: 'all',
        query: '',
        sort: 'relevance',
        selectedId: null,
        filtered: [],
        supported: readSupportedNeeds(),
        markers: new Map(),
        authenticated: Boolean(authenticated),
    };

    const searchInput = document.getElementById('need-search');
    const searchClear = document.querySelector('[data-search-clear]');
    const filterDot = document.querySelector('[data-filter-dot]');
    const filterSheet = document.getElementById('filter-sheet');
    const filterForm = document.getElementById('filter-form');
    const filterOpenButton = document.querySelector('[data-action="open-filter"]');
    const loginGate = document.getElementById('login-gate');
    const loginMessage = loginGate?.querySelector('[data-login-message]');
    const loginSubmit = loginGate?.querySelector('[data-login-submit]');
    const toast = document.getElementById('toast');
    const toastMessage = toast?.querySelector('[data-toast-message]');
    const mapTip = document.querySelector('[data-map-tip]');
    const requestedNeedId = new URLSearchParams(window.location.search).get('need');

    const initialNeed = needs.find((need) => need.id === requestedNeedId) ?? needs[0] ?? null;
    state.selectedId = initialNeed?.id ?? null;

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
    let searchDebounce = null;
    let toastTimeout = null;
    let loginReturnFocus = null;
    let mapErrors = 0;
    let mapErrorShown = false;

    initMap();
    bindEvents();
    applyFilters({ fit: true, syncUrl: false });

    requestAnimationFrame(() => {
        map?.resize();
        fitMapToFiltered();
    });

    function initMap() {
        try {
            map = new MapLibreMap({
                container: mapElement,
                style: mapStyleUrl || DEFAULT_MAP_STYLE_URL,
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
            const resizeObserver = new ResizeObserver(() => map?.resize());
            resizeObserver.observe(mapElement);
        }
    }

    function bindEvents() {
        searchInput?.addEventListener('input', handleSearchInput);
        searchInput?.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && searchInput.value !== '') {
                clearSearch();
            }
        });

        searchClear?.addEventListener('click', clearSearch);

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
                    openLoginGate('Masuk untuk mengakses akun warga nearFind.', actionElement);
                    break;
                case 'submit-need':
                    requireLogin('submit-need', actionElement);
                    break;
                case 'nav-placeholder':
                    showToast(`Halaman ${actionElement.dataset.navLabel ?? 'ini'} segera hadir.`);
                    break;
            }
        });

        filterSheet?.querySelectorAll('[data-filter-close]').forEach((button) => {
            button.addEventListener('click', closeFilterSheet);
        });

        filterSheet?.querySelector('[data-filter-backdrop]')?.addEventListener('click', closeFilterSheet);

        loginGate?.querySelectorAll('[data-login-close]').forEach((button) => {
            button.addEventListener('click', closeLoginGate);
        });

        loginGate?.querySelector('[data-login-backdrop]')?.addEventListener('click', closeLoginGate);

        loginSubmit?.addEventListener('click', () => {
            const loginUrl = loginGate?.dataset.loginUrl;

            if (loginUrl) {
                window.location.assign(loginUrl);
                return;
            }

            closeLoginGate();
        });

        loginGate?.addEventListener('keydown', (event) => {
            if (event.key !== 'Tab') {
                return;
            }

            const focusable = [...loginGate.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')]
                .filter((element) => element.offsetParent !== null);

            if (focusable.length === 0) {
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });

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

            if (loginGate && !loginGate.classList.contains('hidden')) {
                closeLoginGate();
                return;
            }

            if (filterSheet && !filterSheet.classList.contains('hidden')) {
                closeFilterSheet();
            }
        });

        document.querySelector('[data-dismiss-tip]')?.addEventListener('click', () => mapTip?.remove());

        document.querySelector('[data-map-locate]')?.addEventListener('click', locateUser);
        document.querySelector('[data-map-zoom-in]')?.addEventListener('click', () => zoomBy(0.5));
        document.querySelector('[data-map-zoom-out]')?.addEventListener('click', () => zoomBy(-0.5));

        needCardElement.addEventListener('click', (event) => {
            const supportButton = event.target.closest('[data-support-button]');

            if (supportButton) {
                if (requireLogin('support', supportButton)) {
                    toggleSupport(state.selectedId);
                }

                return;
            }

            if (event.target.closest('[data-share-button]')) {
                shareNeed(state.selectedId);
                return;
            }

            const reportButton = event.target.closest('[data-report-button]');

            if (reportButton) {
                if (requireLogin('report', reportButton)) {
                    showToast('Laporan diterima. Terima kasih sudah membantu menjaga kualitas informasi.');
                }

                return;
            }

            if (event.target.closest('[data-reset-filters]')) {
                resetFilters();
            }
        });
    }

    function handleSearchInput() {
        const value = searchInput.value;
        const hasValue = value.trim() !== '';

        setSearchClearVisibility(hasValue);

        window.clearTimeout(searchDebounce);
        searchDebounce = window.setTimeout(() => {
            state.query = value.trim();
            applyFilters({ fit: true });
        }, 180);
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
        state.query = '';
        setSearchClearVisibility(false);
        applyFilters({ fit: true });
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
        applyFilters({ fit: true });
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

            if (query === '') {
                return true;
            }

            return [need.title, need.marker_label, need.location, need.area, need.description, category.label]
                .join(' ')
                .toLocaleLowerCase('id-ID')
                .includes(query);
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

    function sortNeeds(filteredNeeds) {
        const sortedNeeds = [...filteredNeeds];

        switch (state.sort) {
            case 'newest':
                sortedNeeds.sort((first, second) => first.hours_ago - second.hours_ago);
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

    function renderMarkers() {
        if (!map) {
            return;
        }

        tooltip.remove();
        state.markers.forEach((marker) => marker.remove());
        state.markers.clear();

        state.filtered.forEach((need) => {
            const element = createMarkerElement(need, need.id === state.selectedId);
            const marker = new Marker({
                element,
                anchor: 'bottom',
                subpixelPositioning: true,
            })
                .setLngLat([need.longitude, need.latitude])
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
    }

    function createMarkerElement(need, isSelected) {
        const element = document.createElement('div');
        element.className = 'nf-marker-wrapper';
        element.setAttribute('role', 'button');
        element.setAttribute('tabindex', '0');
        element.style.width = isSelected ? '176px' : '46px';
        element.style.height = '48px';
        element.style.zIndex = isSelected ? '420' : '';
        element.innerHTML = markerMarkup(need, isSelected);
        updateMarkerAria(element, need);

        return element;
    }

    function markerMarkup(need, isSelected) {
        const category = categoryById[need.category] ?? fallbackCategory;
        const supporters = displaySupporters(need);
        const markerStyle = `--marker-color:${category.color};--marker-soft:${category.soft_color}`;

        if (isSelected) {
            return `<div class="nf-marker" style="${markerStyle}">
                    <div class="nf-marker-label">
                        <span class="nf-marker-label-icon">${icon(category.icon, 'size-3.5')}</span>
                        <span class="nf-marker-label-title">${escapeHtml(need.marker_label)}</span>
                        <span class="nf-marker-label-count">${supporters}</span>
                    </div>
                    <span class="nf-marker-tail"></span>
                </div>`;
        }

        return `<div class="nf-marker" style="${markerStyle}">
                    <div class="nf-marker-dot">
                        ${icon(category.icon, 'size-4')}
                        <span class="nf-marker-count">${supporters}</span>
                    </div>
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
        if (!map) {
            return;
        }

        tooltip
            .setLngLat([need.longitude, need.latitude])
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

        state.selectedId = need.id;

        state.filtered.forEach((filteredNeed) => refreshMarker(filteredNeed));

        renderCard({ preserveScroll });
        syncUrlSelection();

        if (pan && map) {
            map.panTo([need.longitude, need.latitude], { duration: 350 });
        }
    }

    function toggleSupport(needId) {
        const need = state.needs.find((item) => item.id === needId);

        if (!need) {
            return;
        }

        const wasSupported = state.supported.has(need.id);

        if (wasSupported) {
            state.supported.delete(need.id);
        } else {
            state.supported.add(need.id);
        }

        persistSupportedNeeds();
        refreshMarker(need);
        renderCard({ preserveScroll: true });
        showToast(wasSupported ? 'Dukunganmu dibatalkan.' : 'Dukunganmu tercatat. Terima kasih!');
    }

    function refreshMarker(need) {
        const marker = state.markers.get(need.id);

        if (!marker) {
            return;
        }

        const element = marker.getElement();
        const isSelected = need.id === state.selectedId;
        element.style.width = isSelected ? '176px' : '46px';
        element.style.height = '48px';
        element.style.zIndex = isSelected ? '420' : '';
        element.innerHTML = markerMarkup(need, isSelected);
        updateMarkerAria(element, need);
    }

    function renderCard({ preserveScroll = false } = {}) {
        const need = state.needs.find((item) => item.id === state.selectedId) ?? null;
        const previousScrollTop = preserveScroll ? needCardElement.scrollTop : 0;

        needCardElement.innerHTML = need ? needCardMarkup(need) : emptyCardMarkup();
        needCardElement.scrollTop = previousScrollTop;
    }

    function needCardMarkup(need) {
        const category = categoryById[need.category] ?? fallbackCategory;
        const supported = state.supported.has(need.id);
        const supporters = displaySupporters(need);
        const supportLabel = supported ? 'Kamu sudah mendukung' : 'Saya juga butuh';
        const supportClasses = supported
            ? 'bg-brand-50 text-brand-700 ring-1 ring-brand-700/15 hover:bg-brand-100'
            : 'bg-accent-500 text-white shadow-[0_14px_26px_-16px_rgba(253,118,26,0.8)] hover:bg-accent-600';
        const area = (need.area ?? '').split(',')[0];

        return `
            <article class="need-card-enter">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5">
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[9px] font-semibold uppercase tracking-[0.12em]" style="background:${category.soft_color};color:${category.color}">
                        ${icon(category.icon, 'size-3')}
                        ${escapeHtml(category.label)}
                    </span>

                    <span class="inline-flex items-center gap-1 text-[10.5px] font-medium text-muted">
                        ${icon('clock', 'size-3')}
                        ${escapeHtml(formatTimeAgo(need.hours_ago))}
                    </span>

                    <span class="ml-auto inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-1 text-[9px] font-semibold text-brand-700">
                        ${icon('pin', 'size-3')}
                        ${escapeHtml(area)}
                    </span>
                </div>

                <h2 class="mt-2 text-[21px] font-semibold leading-[1.16] tracking-[-0.03em] text-ink">${escapeHtml(need.title)}</h2>

                <div class="mt-1.5 flex items-start gap-1.5 text-[11px] font-medium leading-snug text-muted">
                    ${icon('pin', 'mt-0.5 size-3.5 shrink-0 text-brand-600')}
                    <span>${escapeHtml(need.location)} <span class="text-brand-700">• ${escapeHtml(formatDistance(need.distance_km))}</span></span>
                </div>

                <p class="mt-2 line-clamp-2 text-[12.5px] leading-[1.6] text-[#4E6360] [@media(max-height:719px)]:hidden">${escapeHtml(need.description)}</p>

                <div class="mt-2.5 flex items-center gap-3 rounded-2xl bg-[#F0F3FF] px-3 py-2">
                    <div class="flex -space-x-2">
                        <span class="flex size-7 items-center justify-center rounded-full border-2 border-white bg-brand-100 text-[8px] font-bold text-brand-800">RW</span>
                        <span class="flex size-7 items-center justify-center rounded-full border-2 border-white bg-[#FFDBCA] text-[8px] font-bold text-[#9A3412]">+${Math.max(supporters - 1, 0)}</span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-1 text-[11px] font-semibold leading-tight text-ink">
                            ${icon('heart', 'size-3.5 shrink-0 fill-[#E11D48] text-[#E11D48]')}
                            <span><span data-support-count>${supporters}</span> warga mendukung</span>
                        </p>
                        <p class="mt-0.5 text-[9.5px] text-muted">kebutuhan ini</p>
                    </div>

                    ${need.opportunity ? '<span class="shrink-0 rounded-lg bg-white/75 px-2 py-1 text-[8.5px] font-semibold text-[#4B5BB5]">Peluang Usaha</span>' : ''}
                </div>

                <div class="mt-2.5 flex gap-2">
                    <button type="button" data-support-button class="flex h-10 flex-1 items-center justify-center gap-2 rounded-xl px-4 text-[12px] font-semibold transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20 active:scale-[0.99] ${supportClasses}">
                        ${icon(supported ? 'check' : 'heart', 'size-4 shrink-0')}
                        <span data-support-label>${supportLabel}</span>
                    </button>

                    <button type="button" data-share-button class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#EEF2FF] text-[#4B5BB5] transition hover:bg-[#E2E8FF] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#4B5BB5]/20" aria-label="Bagikan ${escapeHtml(need.title)}">
                        ${icon('share', 'size-[18px]')}
                    </button>
                </div>

                <p class="mx-auto mt-1.5 max-w-[300px] text-center text-[9.5px] leading-[1.5] text-muted [@media(max-height:779px)]:hidden">Klik untuk mendukung. Kamu akan diminta masuk terlebih dahulu.</p>

                <button type="button" data-report-button class="mt-1 flex w-full items-center justify-start gap-1.5 py-1 pl-1 text-left text-[10px] font-medium text-muted/80 transition hover:text-brand-700 lg:justify-center lg:pl-0">
                    ${icon('flag', 'size-3')}
                    Laporkan ketidaksesuaian
                </button>
            </article>
        `;
    }

    function emptyCardMarkup() {
        return `
            <article class="need-card-enter flex min-h-[260px] flex-col items-center justify-center px-4 py-8 text-center">
                <span class="flex size-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-700">
                    ${icon('search', 'size-7')}
                </span>

                <h2 class="mt-4 text-[17px] font-semibold tracking-[-0.02em] text-ink">Belum ada kebutuhan yang cocok</h2>
                <p class="mt-1.5 max-w-[250px] text-[11.5px] leading-relaxed text-muted">Coba kata kunci lain atau tampilkan kembali semua kategori di sekitar Sukajadi.</p>

                <button type="button" data-reset-filters class="mt-4 inline-flex h-10 items-center justify-center gap-1.5 rounded-xl bg-brand-700 px-4 text-[11px] font-semibold text-white shadow-[0_12px_24px_-16px_rgba(0,92,85,0.8)] transition hover:bg-brand-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20">
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

        const coordinates = state.filtered.map((need) => [need.longitude, need.latitude]);
        const bounds = coordinates.reduce(
            (result, coordinate) => [
                [Math.min(result[0][0], coordinate[0]), Math.min(result[0][1], coordinate[1])],
                [Math.max(result[1][0], coordinate[0]), Math.max(result[1][1], coordinate[1])],
            ],
            [coordinates[0], coordinates[0]],
        );
        const isDesktop = window.matchMedia('(min-width: 1024px)').matches;

        map.fitBounds(bounds, {
            padding: {
                top: isDesktop ? 118 : 104,
                right: isDesktop ? 60 : 24,
                bottom: isDesktop ? 50 : 40,
                left: isDesktop ? 60 : 24,
            },
            maxZoom: isDesktop ? 16 : 15.5,
            linear: true,
            duration: 350,
        });
    }

    function zoomBy(delta) {
        if (!map) {
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
            title: `${need.title} — nearFind`,
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

    function locateUser() {
        if (!map) {
            return;
        }

        if (!('geolocation' in navigator)) {
            showToast('Browser ini tidak mendukung fitur lokasi.');
            return;
        }

        const button = document.querySelector('[data-map-locate]');
        button?.setAttribute('aria-busy', 'true');
        button?.classList.add('opacity-60');

        navigator.geolocation.getCurrentPosition(
            ({ coords }) => {
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
                showToast('Lokasi kamu ditemukan.');

                button?.removeAttribute('aria-busy');
                button?.classList.remove('opacity-60');
            },
            () => {
                showToast('Lokasi tidak dapat diakses. Izinkan akses lokasi di browser.');
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

        if (state.selectedId) {
            url.searchParams.set('need', state.selectedId);
        } else {
            url.searchParams.delete('need');
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
        return need.supporters + (state.supported.has(need.id) ? 1 : 0);
    }

    function readSupportedNeeds() {
        try {
            const storedValue = JSON.parse(window.localStorage.getItem('nearfind:supported-needs') ?? '[]');

            return new Set(Array.isArray(storedValue) ? storedValue : []);
        } catch {
            return new Set();
        }
    }

    function persistSupportedNeeds() {
        try {
            window.localStorage.setItem('nearfind:supported-needs', JSON.stringify([...state.supported]));
        } catch {
            // localStorage can be unavailable in private browsing; the UI still works for this session.
        }
    }

    function formatTimeAgo(hoursAgo) {
        if (!Number.isFinite(hoursAgo) || hoursAgo < 1) {
            return 'Baru saja';
        }

        if (hoursAgo < 24) {
            return `${Math.floor(hoursAgo)} jam lalu`;
        }

        const days = Math.floor(hoursAgo / 24);

        return days === 1 ? '1 hari lalu' : `${days} hari lalu`;
    }

    function formatDistance(distanceKm) {
        return `${Number(distanceKm).toFixed(1).replace('.', ',')} km`;
    }

    function icon(name, className = 'size-5') {
        return `<svg class="${className}" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#nf-${name}"></use></svg>`;
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (character) => {
            const entities = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            };

            return entities[character];
        });
    }
}
