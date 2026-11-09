@php
    $active = $active ?? 'peta';
    $floating = $floating ?? false;
    // The map-first home floats the nav over the map (PR #26); every other page
    // keeps it in the flex column. Classes are precomputed (no `@class`), so the
    // view stays inside the compiled surface.
    $navClass = $floating
        ? 'pointer-events-none absolute inset-x-0 bottom-0 z-[1000] border-t border-white/10 bg-surface/70 pb-[max(0.5rem,env(safe-area-inset-bottom))] pt-1.5 shadow-[0_-18px_45px_-30px_rgba(0,0,0,0.45)] backdrop-blur-2xl'
        : 'relative z-[1000] shrink-0 border-t border-white/10 bg-surface/95 pb-[max(0.4rem,env(safe-area-inset-bottom))] pt-2 shadow-[0_-10px_32px_-28px_rgba(0,0,0,0.5)] backdrop-blur-xl';
    $navInnerClass = $floating
        ? 'pointer-events-auto mx-auto flex w-full max-w-[560px] items-stretch justify-around px-2.5'
        : 'mx-auto flex w-full max-w-[560px] items-stretch justify-around px-2.5';
@endphp
@php
    $isAuthenticated = auth('seal')->check();

    $items = [
        ['key' => 'peta', 'label' => 'Peta', 'icon' => 'map', 'route' => 'home'],
        ['key' => 'jelajahi', 'label' => 'Jelajahi', 'icon' => 'compass', 'route' => 'needs.index'],
        ['key' => 'laporan', 'label' => 'Laporan', 'icon' => 'clipboard', 'route' => 'needs.report'],
        ['key' => 'profil', 'label' => 'Profil', 'icon' => 'user', 'route' => $isAuthenticated ? 'profile.index' : 'auth'],
    ];
@endphp

<nav class="{{ $navClass }}" aria-label="Navigasi utama">
    <div class="{{ $navInnerClass }}">
        @foreach ($items as $item)
            @php
                $isActive = $active === $item['key'];
                $itemClass = 'group flex min-w-0 flex-1 flex-col items-center gap-0.5 rounded-2xl px-2 py-1 text-center outline-none transition-colors focus-visible:ring-4 focus-visible:ring-brand-500/15';
                $iconClass = 'flex h-7 w-12 items-center justify-center rounded-[10px] transition-colors '.($isActive ? 'bg-brand-700 text-canvas shadow-[0_8px_18px_-12px_rgba(0,0,0,0.8)]' : 'text-muted group-hover:bg-brand-50 group-hover:text-brand-200');
                $labelClass = 'text-[10px] font-semibold tracking-[0.01em] '.($isActive ? 'text-brand-200' : 'text-muted group-hover:text-brand-200');
            @endphp

            <a href="{{ route($item['route']) }}" @if ($isActive) aria-current="page" @endif class="{{ $itemClass }}">
                <span class="{{ $iconClass }}">
                    @include('partials.icon', ['name' => $item['icon'], 'class' => 'size-[21px]', 'strokeWidth' => '2'])
                </span>
                <span class="{{ $labelClass }}">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>
