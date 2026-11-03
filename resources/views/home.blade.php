<x-layout title="nearFind — Peta Kebutuhan Warga" active="peta">
    @php
        $nearFindData = [
            'categories' => $categories,
            'needs' => $needs,
            'authenticated' => $authenticated,
            'mapStyleUrl' => $mapStyleUrl,
        ];
    @endphp

    <script type="application/json" id="nearfind-data">@json($nearFindData)</script>

    <div class="relative min-h-0 flex-1">
        <main class="absolute inset-0 overflow-hidden bg-[#EAF4EC]">
            <section class="absolute inset-0 overflow-hidden bg-[#EAF4EC]" aria-label="Peta interaktif kebutuhan warga di Sukajadi dan sekitarnya">
                <div id="map" class="absolute inset-0 z-0" role="region" aria-label="Peta interaktif kebutuhan warga di Sukajadi dan sekitarnya"></div>

                <x-home.map-tip />

                <noscript>
                    <div class="absolute inset-0 z-[900] grid place-items-center bg-canvas/90 p-6 text-center text-sm font-medium text-ink">
                        Aktifkan JavaScript untuk melihat peta interaktif.
                    </div>
                </noscript>
            </section>

            <div class="pointer-events-none absolute inset-x-0 top-0 z-[700] px-3 pt-3 sm:px-4 lg:px-5 lg:pt-4">
                <x-home.header />

                <div class="mt-2.5">
                    <x-home.category-chips :categories="$categories" />
                </div>

                <x-home.map-credit />
                <x-home.map-controls />
            </div>

            <aside
                id="need-panel"
                data-state="collapsed"
                class="nf-need-panel nf-glass-strong absolute inset-x-3 bottom-[calc(5.25rem+env(safe-area-inset-bottom))] z-[600] flex flex-col overflow-hidden rounded-[24px] lg:inset-x-auto lg:bottom-24 lg:right-5 lg:w-[390px]"
                aria-label="Informasi kebutuhan terpilih"
            >
                <div class="relative flex h-10 shrink-0 items-center justify-center px-4">
                    <span class="h-1 w-10 rounded-full bg-brand-900/10"></span>

                    <button
                        type="button"
                        data-card-toggle
                        aria-expanded="false"
                        aria-controls="need-card"
                        aria-label="Buka detail kebutuhan"
                        class="absolute right-3 top-1.5 inline-flex h-7 items-center gap-1 rounded-full bg-white/70 px-2.5 text-[10px] font-semibold text-brand-700 ring-1 ring-brand-900/10 transition hover:bg-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20"
                    >
                        <span data-card-toggle-label>Detail</span>
                        <x-icon name="chevron-down" class="nf-chevron size-3.5 transition-transform duration-200" />
                    </button>
                </div>

                <div
                    id="need-card"
                    class="scrollbar-card min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 pb-5 pt-1 lg:px-5 lg:pb-6"
                    aria-live="polite"
                >
                    <x-home.need-skeleton />
                </div>
            </aside>

            <x-home.fab />
        </main>
    </div>

    <x-home.filter-sheet :categories="$categories" />
    <x-home.login-gate />
</x-layout>
