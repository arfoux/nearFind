@php
    $active = $active ?? 'peta';
    $floating = $floating ?? false;
    // The nav is a compact floating bar on every page: a clear bottom gap
    // keeps the map edge visible and groups the four tabs as one control.
    // R-31: this is the second and last glass surface, so blur stays an accent.
    $navPosition = $floating
        ? 'pointer-events-none absolute bottom-[calc(1rem+env(safe-area-inset-bottom))] left-1/2 z-[1000] w-[calc(100%-1.5rem)] max-w-[520px] -translate-x-1/2'
        : 'relative z-[1000] mx-auto mb-[max(1rem,env(safe-area-inset-bottom))] w-[calc(100%-1.5rem)] max-w-[520px] shrink-0';
@endphp
@php
    // Every tab is a page of its own. Profil points at `/profile`, which needs a
    // session: a guest is redirected by the `auth` middleware to `/auth` (the
    // sign-in page), and comes back to `/profile` once signed in.
    $items = [
        ['key' => 'peta', 'label' => 'Peta', 'icon' => 'map', 'route' => 'home'],
        ['key' => 'jelajahi', 'label' => 'Jelajahi', 'icon' => 'compass', 'route' => 'needs.index'],
        ['key' => 'laporan', 'label' => 'Laporan', 'icon' => 'clipboard', 'route' => 'needs.report'],
        ['key' => 'profil', 'label' => 'Profil', 'icon' => 'user', 'route' => 'profile'],
    ];
@endphp

<div class="{{ $navPosition }}">
    {{-- R-10/R-31: one of exactly two glass hosts. Refraction bends the map while
         Gaussian blur frosts sharp labels; anchors stay direct children so the
         library can clone their copy into the indicator. --}}
    <lg-navbar
        class="nf-nav pointer-events-auto mx-auto flex w-full max-w-[560px] items-stretch justify-around px-2.5"
        tone="light"
        radius="22"
        refraction="22"
        bezel="0.45"
        curvature="4"
        chroma="0.16"
        blur="3.5"
        specular="0.95"
        specular-width="2.4"
        light-angle="-90"
        aria-label="Navigasi utama"
    >
        @foreach ($items as $item)
            <a
                href="{{ route($item['route']) }}"
                data-nav-link
                data-nav-key="{{ $item['key'] }}"
                @if ($active === $item['key']) aria-current="page" @endif
            >
                <span data-nav-icon>
                    @include('partials.icon', ['name' => $item['icon'], 'class' => 'size-[21px]', 'strokeWidth' => '2'])
                </span>
                <span data-nav-label>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </lg-navbar>
</div>
