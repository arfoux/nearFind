<form data-search-form class="relative" role="search">
    <label for="need-search" class="sr-only">Cari area, kelurahan, atau kebutuhan</label>

    <div data-search-box class="flex h-12 cursor-text items-center gap-2 rounded-full border border-ink/10 bg-surface/70 px-3.5 shadow-[0_12px_30px_-22px_rgba(0,0,0,0.65)] transition focus-within:border-brand-500/40 focus-within:bg-surface/80 focus-within:ring-4 focus-within:ring-brand-500/10 lg:h-[52px] lg:gap-3 lg:px-5">
        @include('partials.icon', ['name' => 'search', 'class' => 'size-[19px] shrink-0 text-brand-300 lg:size-5'])

        <input
            id="need-search"
            type="search"
            inputmode="search"
            autocomplete="off"
            placeholder="Cari area, kelurahan, atau kebutuhan"
            class="min-w-0 flex-1 bg-transparent text-[13px] font-medium text-ink outline-none placeholder:text-muted/80 lg:text-sm"
        >

        <button
            type="button"
            data-search-clear
            class="hidden size-11 shrink-0 items-center justify-center rounded-full text-muted transition hover:bg-canvas hover:text-brand-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30"
            aria-label="Hapus pencarian"
        >
            @include('partials.icon', ['name' => 'close', 'class' => 'size-3.5'])
        </button>

        <span class="h-6 w-px shrink-0 bg-ink/10"></span>

        <button
            type="button"
            data-action="open-filter"
            class="relative flex size-11 shrink-0 items-center justify-center rounded-full text-brand-200 transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15"
            aria-label="Buka filter kebutuhan"
            aria-haspopup="dialog"
            aria-controls="filter-sheet"
        >
            @include('partials.icon', ['name' => 'sliders', 'class' => 'size-[19px]'])
            <span data-filter-dot class="absolute right-1.5 top-1.5 hidden size-2 rounded-full bg-accent-500 ring-2 ring-surface"></span>
        </button>
    </div>

        <div
            data-place-top-results
            hidden
            class="pointer-events-auto absolute inset-x-0 top-[calc(100%+0.5rem)] z-[760] max-h-[min(55dvh,20rem)] overflow-y-auto overscroll-contain rounded-[20px] bg-surface p-2 shadow-[0_20px_50px_-30px_rgba(0,0,0,0.9)] ring-1 ring-ink/10"
        >
            <p data-place-top-loading hidden class="px-3 py-2 text-[11.5px] text-muted">Mencari tempat…</p>
            <p data-place-top-error hidden class="px-3 py-2 text-[11.5px] font-medium text-danger">Pencarian gagal. Coba lagi.</p>
            <ul data-place-top-list role="list" class="space-y-1"></ul>
            <p data-place-top-empty hidden class="px-3 py-2 text-[11.5px] leading-relaxed text-muted">Belum ada tempat yang cocok.</p>
        </div>
</form>
