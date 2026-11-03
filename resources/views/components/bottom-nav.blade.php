@props(['active' => 'peta'])

@php
    $isAuthenticated = auth('seal')->check();

    $items = [
        ['key' => 'peta', 'label' => 'Peta', 'icon' => 'map', 'route' => 'home'],
        ['key' => 'jelajahi', 'label' => 'Jelajahi', 'icon' => 'compass', 'route' => null],
        ['key' => 'laporan', 'label' => 'Laporan', 'icon' => 'clipboard', 'route' => null],
        ['key' => 'profil', 'label' => 'Profil', 'icon' => 'user', 'route' => $isAuthenticated ? 'profile.index' : 'login'],
    ];
@endphp

<nav class="relative z-[1000] shrink-0 border-t border-brand-900/10 bg-white/95 pb-[max(0.4rem,env(safe-area-inset-bottom))] pt-2 shadow-[0_-10px_32px_-28px_rgba(0,60,55,0.5)] backdrop-blur-xl" aria-label="Navigasi utama">
    <div class="mx-auto flex w-full max-w-[560px] items-stretch justify-around px-2.5">
        @foreach ($items as $item)
            @php
                $isActive = $active === $item['key'];
                $itemClass = 'group flex min-w-0 flex-1 flex-col items-center gap-0.5 rounded-2xl px-2 py-1 text-center outline-none transition-colors focus-visible:ring-4 focus-visible:ring-brand-500/15';
                $iconClass = 'flex h-7 w-12 items-center justify-center rounded-full transition-colors '.($isActive ? 'bg-brand-50 text-brand-700' : 'text-muted group-hover:bg-canvas group-hover:text-brand-700');
                $labelClass = 'text-[10px] font-semibold tracking-[0.01em] '.($isActive ? 'text-brand-700' : 'text-muted group-hover:text-brand-700');
            @endphp

            @if ($item['route'])
                <a href="{{ route($item['route']) }}" @if ($isActive) aria-current="page" @endif class="{{ $itemClass }}">
                    <span class="{{ $iconClass }}">
                        <x-icon :name="$item['icon']" class="size-[21px]" stroke-width="2" />
                    </span>
                    <span class="{{ $labelClass }}">{{ $item['label'] }}</span>
                </a>
            @else
                <button
                    type="button"
                    data-action="nav-placeholder"
                    data-nav-label="{{ $item['label'] }}"
                    class="{{ $itemClass }}"
                >
                    <span class="{{ $iconClass }}">
                        <x-icon :name="$item['icon']" class="size-[21px]" stroke-width="1.8" />
                    </span>
                    <span class="{{ $labelClass }}">{{ $item['label'] }}</span>
                </button>
            @endif
        @endforeach
    </div>
</nav>
