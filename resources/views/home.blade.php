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
                <div class="pointer-events-none absolute inset-x-0 top-0 h-5 bg-canvas lg:h-6" aria-hidden="true"></div>

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
                class="nf-need-panel nf-glass-strong absolute inset-x-3 bottom-[calc(4.75rem+env(safe-area-inset-bottom))] z-[600] flex flex-col overflow-hidden rounded-[20px] lg:inset-x-auto lg:bottom-24 lg:right-[var(--nf-panel-right)] lg:w-[var(--nf-panel-width)]"
                aria-label="Informasi kebutuhan terpilih"
            >
                <div id="need-peek" class="flex shrink-0 items-center gap-3 px-4 py-3" aria-live="polite">
                    <span class="flex size-10 shrink-0 animate-pulse items-center justify-center rounded-[14px] bg-brand-50 text-brand-700"></span>
                    <div class="min-w-0 flex-1 space-y-2">
                        <span class="block h-2 w-24 animate-pulse rounded-full bg-brand-50"></span>
                        <span class="block h-3 w-40 animate-pulse rounded-full bg-canvas"></span>
                    </div>
                </div>

                <div
                    id="need-card"
                    class="scrollbar-card min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 pb-5 pt-3 lg:px-5 lg:pb-6"
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
