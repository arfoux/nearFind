@props(['categories'])

<div class="pointer-events-none absolute inset-x-0 top-0 z-[520] bg-gradient-to-b from-canvas via-canvas/90 to-transparent pb-7 pt-3">
    <div class="no-scrollbar pointer-events-auto flex gap-2 overflow-x-auto px-4 pb-1 sm:px-5 lg:px-6" role="group" aria-label="Filter kategori kebutuhan">
        @foreach ($categories as $category)
            <button
                type="button"
                data-category="{{ $category['id'] }}"
                aria-pressed="{{ $category['id'] === 'all' ? 'true' : 'false' }}"
                class="category-chip {{ $category['id'] === 'all' ? 'is-active' : '' }}"
            >
                @if ($category['id'] !== 'all')
                    <span class="chip-icon" style="--chip-color: {{ $category['color'] }}; --chip-soft: {{ $category['soft_color'] }};">
                        @include('partials.icon', ['name' => $category['icon'], 'class' => 'size-3.5'])
                    </span>
                @endif

                <span>{{ $category['label'] }}</span>
            </button>
        @endforeach
    </div>
</div>
