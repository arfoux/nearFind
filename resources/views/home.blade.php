<x-layout title="nearFind — Peta Kebutuhan Warga" active="peta">
    @php
        $nearFindData = [
            'categories' => $categories,
            'needs' => $needs,
            'authenticated' => $authenticated,
        ];
    @endphp

    <script type="application/json" id="nearfind-data">@json($nearFindData)</script>

    <div class="relative flex min-h-0 flex-1 flex-col">
        <x-home.header />

        <main class="relative flex min-h-0 flex-1 flex-col lg:flex-row lg:gap-5 lg:px-6 lg:pb-5">
            <section class="relative min-h-[150px] flex-1 overflow-hidden bg-[#EAF4EC] lg:rounded-[28px] lg:shadow-[0_22px_60px_-38px_rgba(0,60,55,0.45)]">
                <div id="map" class="absolute inset-0 z-0" role="region" aria-label="Peta interaktif kebutuhan warga di Sukajadi dan sekitarnya"></div>

                <x-home.category-chips :categories="$categories" />
                <x-home.map-controls />
                <x-home.map-credit />
                <x-home.map-tip />

                <div class="pointer-events-none absolute inset-x-0 bottom-0 z-[510] h-20 bg-gradient-to-t from-canvas/80 to-transparent lg:hidden"></div>

                <noscript>
                    <div class="absolute inset-0 z-[600] grid place-items-center bg-canvas/90 p-6 text-center text-sm font-medium text-ink">
                        Aktifkan JavaScript untuk melihat peta interaktif.
                    </div>
                </noscript>
            </section>

            <aside
                class="relative z-[600] -mt-5 flex h-[42dvh] min-h-0 shrink-0 flex-col overflow-hidden rounded-t-[28px] bg-white shadow-[0_-18px_50px_-30px_rgba(0,60,55,0.45)] lg:mt-0 lg:h-auto lg:max-h-none lg:w-[390px] lg:rounded-[28px] lg:shadow-[0_22px_60px_-40px_rgba(0,60,55,0.4)]"
                aria-label="Informasi kebutuhan terpilih"
            >
                <div class="flex shrink-0 justify-center pb-1 pt-2.5 lg:pt-3">
                    <span class="h-1 w-10 rounded-full bg-brand-900/10"></span>
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
