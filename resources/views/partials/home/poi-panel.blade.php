{{-- Read-only detail for an OpenStreetMap point. The panel is hidden until a
     pin is chosen. It never posts and has no hidden form inputs; saving only
     prefills the existing location sheet. --}}
<aside
    data-poi-panel
    hidden
    aria-hidden="true"
    aria-labelledby="poi-panel-title"
    class="nf-poi-panel nf-solid-panel absolute inset-x-3 bottom-[calc(5.5rem+env(safe-area-inset-bottom))] z-[670] overflow-hidden rounded-[20px] lg:inset-x-auto lg:bottom-24 lg:right-5 lg:w-[390px]"
>
    <div class="flex items-start gap-3 px-4 py-3">
        <span class="flex size-10 shrink-0 items-center justify-center rounded-[14px] bg-brand-50 text-brand-200">
            @include('partials.icon', ['name' => 'map', 'class' => 'size-5'])
        </span>

        <div class="min-w-0 flex-1">
            <p class="text-[9px] font-semibold uppercase tracking-[0.16em] text-brand-300">Tempat dari OpenStreetMap</p>
            <h2 id="poi-panel-title" data-poi-panel-title class="mt-0.5 truncate text-[15px] font-semibold tracking-[-0.02em] text-ink">Detail tempat</h2>
            <p data-poi-panel-meta class="mt-0.5 truncate text-[11px] text-muted">Pilih pin tempat di peta.</p>
        </div>

        <button
            type="button"
            data-poi-panel-close
            class="flex size-11 shrink-0 items-center justify-center rounded-full bg-canvas text-muted transition hover:bg-brand-50 hover:text-brand-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15"
            aria-label="Tutup detail tempat"
        >
            @include('partials.icon', ['name' => 'close', 'class' => 'size-4'])
        </button>
    </div>

    <dl data-poi-panel-body class="nf-poi-body scrollbar-card max-h-[min(48dvh,26rem)] overflow-y-auto overscroll-contain px-4 pb-2 pt-1 lg:px-5"></dl>

    <div class="nf-poi-actions flex items-center gap-2 px-4 py-3 lg:px-5">
        <button
            type="button"
            data-poi-save
            data-action="add-place"
            class="nf-poi-action nf-poi-action--primary flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl px-3 text-[11.5px] font-semibold transition hover:bg-brand-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40"
        >
            @include('partials.icon', ['name' => 'plus', 'class' => 'size-4'])
            <span>Jadikan tempat</span>
        </button>

        <a
            data-poi-route
            href="#"
            target="_blank"
            rel="noopener noreferrer"
            class="nf-poi-action flex min-h-11 flex-1 items-center justify-center rounded-xl border border-[var(--nf-border)] bg-canvas px-3 text-[11.5px] font-semibold text-ink transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40"
        >Buka rute</a>
    </div>
</aside>
