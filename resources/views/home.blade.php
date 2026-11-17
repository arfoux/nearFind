@extends('layouts.app')
@php $title = 'nearFind: Peta Kebutuhan Warga'; $active = 'peta'; @endphp
@section('content')
    @php
        $nearFindData = [
            'categories' => $categories,
            'needs' => $needs,
            'locations' => $locations ?? [],
            'authenticated' => $authenticated,
            'mapStyleUrl' => $mapStyleUrl,
            'mapDarkStyleUrl' => $mapDarkStyleUrl ?? $mapStyleUrl,
            'mapLightStyleUrl' => $mapLightStyleUrl ?? $mapStyleUrl,
            'poi' => $poi ?? config('needs.poi'),
            // A flash survives the redirect back to the map after a submission;
            // app.js turns it into the transient success toast.
            'flash' => session('status'),
        ];
    @endphp

    <script type="application/json" id="nearfind-data">@json($nearFindData)</script>

    <div class="relative min-h-0 flex-1">
        <main class="absolute inset-0 overflow-hidden">
            <section class="absolute inset-0 overflow-hidden" aria-label="Peta interaktif kebutuhan warga di Sukajadi dan sekitarnya">
                <div id="map" class="absolute inset-0 z-0" role="region" aria-label="Peta interaktif kebutuhan warga di Sukajadi dan sekitarnya"></div>

                {{-- The crosshair marks the exact point captured when either map
                     action is pressed. It stays fixed to the canvas centre,
                     never takes pointer events, and the hidden copy below
                     explains its purpose to screen readers. --}}
                <div class="nf-map-reticle pointer-events-none absolute left-1/2 top-1/2 z-[100] -translate-x-1/2 -translate-y-1/2" aria-hidden="true">
                    <span class="nf-map-reticle__arm nf-map-reticle__arm--x"></span>
                    <span class="nf-map-reticle__arm nf-map-reticle__arm--y"></span>
                    <span class="nf-map-reticle__dot"></span>
                </div>
                <p class="sr-only">Tanda + di tengah peta menunjukkan titik yang dipakai saat kamu menekan Ajukan Kebutuhan atau Tambah Tempat. Geser peta untuk memindahkan titik ini.</p>

                {{-- R-27: honest style-load status, hidden again once the map is ready. --}}
                <p
                    data-map-loading
                    role="status"
                    aria-live="polite"
                    hidden
                    class="nf-solid-panel pointer-events-none absolute left-1/2 top-[9.5rem] z-[540] -translate-x-1/2 rounded-full px-3 py-1.5 text-[10.5px] font-medium text-ink"
                >Memuat peta…</p>

                @include('partials.home/map-tip', [])

                <noscript>
                    <div class="absolute inset-0 z-[900] grid place-items-center bg-canvas p-6 text-center text-sm font-medium text-ink">
                        Aktifkan JavaScript untuk melihat peta interaktif.
                    </div>
                </noscript>
            </section>

            <div class="pointer-events-none absolute inset-x-0 top-0 z-[700] px-3 pt-3 sm:px-4 lg:px-5 lg:pt-4">
                @include('partials.home/header', ['sealUser' => $sealUser])

                <div class="mt-2.5">
                    @include('partials.home/category-chips', ['categories' => $categories])
                </div>

                @include('partials.home/map-credit', [])
                @include('partials.home/map-controls', [])
            </div>

            <aside
                id="need-panel"
                data-state="collapsed"
                class="nf-need-panel nf-solid-panel absolute inset-x-3 bottom-[calc(5.5rem+env(safe-area-inset-bottom))] z-[600] flex flex-col overflow-hidden rounded-[20px] lg:inset-x-auto lg:bottom-24 lg:right-5 lg:w-[390px]"
                aria-label="Informasi kebutuhan terpilih"
            >
                <div id="need-peek" class="flex shrink-0 items-center gap-3 px-4 py-3" aria-live="polite">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-[14px] bg-brand-50 text-brand-200"></span>
                    <div class="min-w-0 flex-1 space-y-2">
                        <span class="block h-2 w-24 rounded-full bg-brand-50"></span>
                        <span class="block h-3 w-40 rounded-full bg-canvas"></span>
                    </div>
                </div>

                <div
                    id="need-card"
                    class="scrollbar-card min-h-0 overflow-y-auto overscroll-contain px-4 pb-5 pt-3 lg:px-5 lg:pb-6"
                    aria-live="polite"
                >
                    @include('partials.home/need-skeleton', [])
                </div>
            </aside>

            {{-- Saved places get their own panel so a location never borrows a
                 need's detail. JS swaps this with #need-panel. Native `hidden`
                 keeps it out of the tab order until a pin is selected. --}}
            <aside
                data-location-panel
                hidden
                class="nf-solid-panel absolute inset-x-3 bottom-[calc(5.5rem+env(safe-area-inset-bottom))] z-[650] overflow-hidden rounded-[20px] lg:inset-x-auto lg:bottom-24 lg:right-5 lg:w-[390px]"
                aria-label="Detail tempat warga"
            >
                <div class="flex items-start gap-3 px-4 py-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-[14px] bg-brand-50 text-brand-200">
                        @include('partials.icon', ['name' => 'pin', 'class' => 'size-5'])
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="text-[9px] font-semibold uppercase tracking-[0.16em] text-brand-300">Tempat warga</p>
                        <h2 data-location-panel-title class="mt-0.5 truncate text-[15px] font-semibold tracking-[-0.02em] text-ink">Detail tempat</h2>
                        <p data-location-panel-meta class="mt-0.5 truncate text-[11px] text-muted">Pilih pin tempat di peta.</p>
                    </div>

                    <button
                        type="button"
                        data-location-panel-close
                        class="flex size-11 shrink-0 items-center justify-center rounded-full bg-canvas text-muted transition hover:bg-brand-50 hover:text-brand-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15"
                        aria-label="Tutup detail tempat"
                    >
                        @include('partials.icon', ['name' => 'close', 'class' => 'size-4'])
                    </button>
                </div>

                <div data-location-panel-body class="scrollbar-card min-h-0 max-h-[min(56dvh,32rem)] overflow-y-auto overscroll-contain px-4 pb-5 pt-1 lg:px-5 lg:pb-6"></div>
            </aside>
            @include('partials.home.poi-panel')

            @include('partials.home/fab', [])
        </main>
    </div>

    @include('partials.home/filter-sheet', ['categories' => $categories])
    @include('partials.home/location-sheet', ['categories' => $categories])

    @if ($authenticated)
        @include('partials.home/submit-sheet', ['categories' => $categories])
    @else
        @include('partials.home/login-gate', [])
    @endif
@endsection
