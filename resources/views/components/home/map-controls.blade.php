<div data-map-controls class="nf-map-controls pointer-events-auto absolute right-3 top-full z-[530] mt-2 flex flex-col items-end gap-2 transition-[opacity,transform] duration-200 lg:right-[calc(390px+3.5rem)]">
    <button
        type="button"
        data-map-locate
        class="map-control"
        aria-label="Temukan lokasi saya"
        title="Temukan lokasi saya"
    >
        <x-icon name="locate" class="size-[18px]" />
    </button>

    <div class="nf-glass-subtle flex flex-col overflow-hidden rounded-[14px]">
        <button
            type="button"
            data-map-zoom-in
            class="map-control map-control--flush"
            aria-label="Perbesar peta"
            title="Perbesar peta"
        >
            <x-icon name="plus" class="size-[17px]" />
        </button>

        <span class="h-px w-full bg-brand-900/10"></span>

        <button
            type="button"
            data-map-zoom-out
            class="map-control map-control--flush"
            aria-label="Perkecil peta"
            title="Perkecil peta"
        >
            <x-icon name="minus" class="size-[17px]" />
        </button>
    </div>
</div>
