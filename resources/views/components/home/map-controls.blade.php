<div class="pointer-events-none absolute right-3 top-[78px] z-[530] flex flex-col items-end gap-2 lg:right-4 lg:top-[82px] [@media(max-height:719px)]:top-[68px]">
    <button
        type="button"
        data-map-locate
        class="map-control"
        aria-label="Temukan lokasi saya"
        title="Temukan lokasi saya"
    >
        @include('partials.icon', ['name' => 'locate', 'class' => 'size-[18px]'])
    </button>

    <div class="flex flex-col overflow-hidden rounded-[14px] bg-white/95 shadow-[0_10px_24px_-16px_rgba(0,60,55,0.55)] ring-1 ring-brand-900/10 backdrop-blur">
        <button
            type="button"
            data-map-zoom-in
            class="map-control map-control--flush"
            aria-label="Perbesar peta"
            title="Perbesar peta"
        >
            @include('partials.icon', ['name' => 'plus', 'class' => 'size-[17px]'])
        </button>

        <span class="h-px w-full bg-brand-900/10"></span>

        <button
            type="button"
            data-map-zoom-out
            class="map-control map-control--flush"
            aria-label="Perkecil peta"
            title="Perkecil peta"
        >
            @include('partials.icon', ['name' => 'minus', 'class' => 'size-[17px]'])
        </button>
    </div>
</div>
