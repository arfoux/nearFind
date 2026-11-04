<div
    data-map-tip
    class="absolute inset-x-3 bottom-7 z-[525] hidden items-center gap-2.5 rounded-2xl border border-white/70 bg-white/95 px-3 py-2.5 shadow-[0_14px_30px_-20px_rgba(0,60,55,0.5)] backdrop-blur lg:inset-x-4 lg:bottom-24 lg:flex lg:max-w-[370px]"
>
    <span class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
        @include('partials.icon', ['name' => 'pin', 'class' => 'size-4'])
    </span>

    <p class="min-w-0 flex-1 text-[10.5px] font-medium leading-[1.45] text-ink">
        Pilih pin untuk melihat kebutuhan jasa &amp; fasilitas di sekitar.
    </p>

    <button
        type="button"
        data-dismiss-tip
        class="flex size-6 shrink-0 items-center justify-center rounded-full text-muted transition hover:bg-canvas hover:text-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30"
        aria-label="Tutup petunjuk peta"
    >
        @include('partials.icon', ['name' => 'close', 'class' => 'size-3.5'])
    </button>
</div>
