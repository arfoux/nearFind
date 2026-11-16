{{-- Two explicit actions, one per kind of map data. `.nf-fab` stays on the
     wrapper so the existing "hide FAB while the need panel is expanded" CSS
     still matches the sibling selector. --}}
<div class="nf-fab absolute bottom-[calc(10.25rem+env(safe-area-inset-bottom))] left-1/2 z-[800] flex -translate-x-1/2 flex-col items-center gap-2 lg:bottom-24 lg:left-auto lg:right-[calc(390px+2.5rem)] lg:items-end lg:translate-x-0">
    <button
        type="button"
        data-action="submit-need"
        class="group inline-flex h-12 items-center gap-2 rounded-full bg-brand-700 pl-2 pr-5 text-[12px] font-semibold text-white shadow-[0_18px_34px_-14px_rgba(0,0,0,0.65)] ring-1 ring-ink/10 transition hover:bg-brand-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/25 active:scale-[0.98]"
    >
        <span class="flex size-8 items-center justify-center rounded-full bg-brand-500/40 transition-colors group-hover:bg-brand-500/60">
            @include('partials.icon', ['name' => 'plus', 'class' => 'size-[18px]', 'strokeWidth' => '2.2'])
        </span>
        <span>Ajukan Kebutuhan</span>
    </button>

    <button
        type="button"
        data-action="add-place"
        class="group inline-flex h-12 items-center gap-2 rounded-full bg-surface-2 pl-2 pr-5 text-[12px] font-semibold text-ink shadow-[0_18px_34px_-14px_rgba(0,0,0,0.65)] ring-1 ring-ink/10 transition hover:bg-surface focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/25 active:scale-[0.98]"
    >
        <span class="flex size-8 items-center justify-center rounded-full bg-brand-50 text-brand-200 transition-colors group-hover:bg-brand-100">
            @include('partials.icon', ['name' => 'pin', 'class' => 'size-[18px]', 'strokeWidth' => '2.2'])
        </span>
        <span>Tambah Tempat</span>
    </button>
</div>
