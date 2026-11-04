<div class="relative">
    <label for="need-search" class="sr-only">Cari area, kelurahan, atau kebutuhan</label>

    <div class="flex h-12 items-center gap-2.5 rounded-full border border-white/70 bg-white/55 px-3.5 shadow-[0_12px_30px_-22px_rgba(0,60,55,0.65)] transition focus-within:border-brand-500/40 focus-within:bg-white/70 focus-within:ring-4 focus-within:ring-brand-500/10 lg:h-[52px] lg:gap-3 lg:px-5">
        @include('partials.icon', ['name' => 'search', 'class' => 'size-[19px] shrink-0 text-brand-600 lg:size-5'])

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
            class="hidden size-6 shrink-0 items-center justify-center rounded-full text-muted transition hover:bg-canvas hover:text-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30"
            aria-label="Hapus pencarian"
        >
            @include('partials.icon', ['name' => 'close', 'class' => 'size-3.5'])
        </button>

        <span class="h-6 w-px shrink-0 bg-brand-900/10"></span>

        <button
            type="button"
            data-action="open-filter"
            class="relative flex size-8 shrink-0 items-center justify-center rounded-full text-brand-700 transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15"
            aria-label="Buka filter kebutuhan"
            aria-haspopup="dialog"
            aria-controls="filter-sheet"
        >
            @include('partials.icon', ['name' => 'sliders', 'class' => 'size-[19px]'])
            <span data-filter-dot class="absolute right-1 top-1 hidden size-2 rounded-full bg-accent-500 ring-2 ring-white"></span>
        </button>
    </div>
</div>
