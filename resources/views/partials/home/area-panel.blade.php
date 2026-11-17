{{-- Read-only detail for an OpenStreetMap area. The panel is hidden until an
     area is chosen on the map. It never posts and has no hidden form inputs;
     saving only prefills the existing location sheet, so the login gate stays
     in front of any submission. --}}
<aside
    data-area-panel
    hidden
    aria-hidden="true"
    aria-labelledby="area-panel-title"
    class="nf-area-panel nf-solid-panel absolute inset-x-3 bottom-[calc(5.5rem+env(safe-area-inset-bottom))] z-[720] overflow-hidden rounded-[20px] lg:inset-x-auto lg:bottom-24 lg:right-5 lg:w-[390px]"
>
    <div class="flex items-start gap-3 px-4 py-3">
        <span class="flex size-10 shrink-0 items-center justify-center rounded-[14px] bg-brand-50 text-brand-200">
            @include('partials.icon', ['name' => 'map', 'class' => 'size-5'])
        </span>

        <div class="min-w-0 flex-1">
            <p data-area-panel-kicker class="text-[9px] font-semibold uppercase tracking-[0.16em] text-brand-300">Daerah dari OpenStreetMap</p>
            <h2 id="area-panel-title" data-area-panel-title class="mt-0.5 truncate text-[15px] font-semibold tracking-[-0.02em] text-ink">Detail daerah</h2>
            <p data-area-panel-meta class="mt-0.5 truncate text-[11px] text-muted">Pilih area di peta.</p>
        </div>

        <button
            type="button"
            data-area-panel-close
            class="flex size-11 shrink-0 items-center justify-center rounded-full bg-canvas text-muted transition hover:bg-brand-50 hover:text-brand-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15"
            aria-label="Tutup detail daerah"
        >
            @include('partials.icon', ['name' => 'close', 'class' => 'size-4'])
        </button>
    </div>

    <dl data-area-panel-body aria-live="polite" class="nf-area-body scrollbar-card max-h-[min(48dvh,26rem)] overflow-y-auto overscroll-contain px-4 pb-2 pt-1 lg:px-5"></dl>

    <div class="nf-area-actions flex items-center gap-2 border-t border-[var(--nf-divider)] px-4 py-3 lg:px-5">
        <button
            type="button"
            data-area-save
            data-action="add-place"
            class="nf-area-action flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-brand-50 px-3 text-[11.5px] font-semibold text-brand-200 transition hover:bg-brand-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40"
        >
            @include('partials.icon', ['name' => 'plus', 'class' => 'size-4'])
            <span>Jadikan tempat</span>
        </button>

        <a
            data-area-route
            href="https://www.openstreetmap.org/"
            target="_blank"
            rel="noopener noreferrer"
            class="nf-area-action flex min-h-11 flex-1 items-center justify-center rounded-xl border border-[var(--nf-border)] bg-canvas px-3 text-[11.5px] font-semibold text-ink transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40"
        >Buka di OpenStreetMap</a>
    </div>
</aside>
