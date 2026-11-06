@php
    $categories = $categories ?? null;
@endphp
<div class="no-scrollbar pointer-events-auto flex gap-2 overflow-x-auto px-0.5 pb-0.5 pt-0.5" role="group" aria-label="Filter kategori kebutuhan">
        @foreach ($categories as $category)
            <button
                type="button"
                data-category="{{ $category['id'] }}"
                aria-pressed="{{ $category['id'] === 'all' ? 'true' : 'false' }}"
                class="category-chip {{ $category['id'] === 'all' ? 'is-active' : '' }}"
            >
                @if ($category['id'] !== 'all')
                    <span class="chip-icon" style="--chip-color: {{ $category['color'] }}">
                        @include('partials.icon', ['name' => $category['icon'], 'class' => 'size-3.5'])
                    </span>
                @endif

                <span>{{ $category['label'] }}</span>
            </button>
    @endforeach
</div>
