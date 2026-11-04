@props(['active' => 'peta', 'floating' => false])

@php
    $isAuthenticated = auth('seal')->check();

    $items = [
        ['key' => 'peta', 'label' => 'Peta', 'icon' => 'map', 'route' => 'home'],
        ['key' => 'jelajahi', 'label' => 'Jelajahi', 'icon' => 'compass', 'route' => null],
        ['key' => 'laporan', 'label' => 'Laporan', 'icon' => 'clipboard', 'route' => null],
        ['key' => 'profil', 'label' => 'Profil', 'icon' => 'user', 'route' => $isAuthenticated ? 'profile.index' : 'login'],
    ];
@endphp

<nav
    @class([
        'z-[1000]',
        'pointer-events-none absolute inset-x-0 bottom-0 border-t border-white/60 bg-white/80 pb-[max(0.5rem,env(safe-area-inset-bottom))] pt-1.5 shadow-[0_-18px_45px_-30px_rgba(0,60,55,0.45)] backdrop-blur-xl' => $floating,
        'relative shrink-0 border-t border-brand-900/10 bg-white/95 pb-[max(0.4rem,env(safe-area-inset-bottom))] pt-2 shadow-[0_-10px_32px_-28px_rgba(0,60,55,0.5)] backdrop-blur-xl' => ! $floating,
    ])
    aria-label="Navigasi utama"
>
    <div
        @class([
            'mx-auto flex w-full items-stretch justify-around',
            'pointer-events-auto max-w-[560px] px-2.5' => $floating,
            'max-w-[560px] px-2.5' => ! $floating,
        ])
    >
        @foreach ($items as $item)
            @php
                $isActive = $active === $item['key'];
                $itemClass = 'group flex min-w-0 flex-1 flex-col items-center gap-0.5 rounded-2xl px-2 py-1 text-center outline-none transition-colors focus-visible:ring-4 focus-visible:ring-brand-500/15';
                $iconClass = 'flex h-7 w-12 items-center justify-center rounded-[10px] transition-colors '.($isActive ? 'bg-brand-700 text-white shadow-[0_8px_18px_-12px_rgba(0,92,85,0.8)]' : 'text-muted group-hover:bg-brand-50 group-hover:text-brand-700');
                $labelClass = 'text-[10px] font-semibold tracking-[0.01em] '.($isActive ? 'text-brand-700' : 'text-muted group-hover:text-brand-700');
            @endphp

            @if ($item['route'])
                <a href="{{ route($item['route']) }}" @if ($isActive) aria-current="page" @endif class="{{ $itemClass }}">
                    <span class="{{ $iconClass }}">
                        @include('partials.icon', ['name' => $item['icon'], 'class' => 'size-[21px]', 'strokeWidth' => '2'])
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
                        @include('partials.icon', ['name' => $item['icon'], 'class' => 'size-[21px]', 'strokeWidth' => '1.8'])
                    </span>
                    <span class="{{ $labelClass }}">{{ $item['label'] }}</span>
                </button>
            @endif
        @endforeach
    </div>
</nav>
