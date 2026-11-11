@php
    $title = $title ?? 'nearFind — Peta Kebutuhan Warga';
    $active = $active ?? 'peta';

    // The map page IS the canvas: the MapLibre surface runs edge to edge under a
    // full-bleed shell and every control floats on glass above it. Jelajahi and
    // Laporan share that full-bleed shell so their content owns the whole
    // viewport. The account page keeps the framed card.
    $isMap = $active === 'peta';
    $isFullBleed = $isMap || in_array($active, ['jelajahi', 'laporan'], true);
    $bodyClass = $isMap
        ? 'h-dvh w-full overflow-hidden bg-transparent text-ink antialiased'
        : 'app-background h-dvh w-full overflow-hidden text-ink antialiased';
    $shellClass = match (true) {
        $isMap => 'relative flex h-dvh min-h-dvh w-full flex-col overflow-hidden',
        $isFullBleed => 'relative flex h-dvh min-h-dvh w-full flex-col overflow-hidden bg-canvas',
        default => 'relative mx-auto flex h-dvh w-full flex-col overflow-hidden bg-canvas sm:my-4 sm:h-[calc(100dvh-2rem)] sm:max-w-[600px] sm:rounded-[2.25rem] sm:shadow-[0_30px_90px_-40px_rgba(0,0,0,0.55)] sm:ring-1 sm:ring-white/10 lg:my-6 lg:h-[calc(100dvh-3rem)] lg:max-w-[1180px]',
    };

    // The page a tab request is waiting for. Following a nav link rewrites
    // `tab` on the live shell (app.js keeps it in sync with the address bar).
    $tab = request()->query('tab', $active);
@endphp

<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0B0E12">
    <meta name="description" content="nearFind membantu warga menemukan dan melaporkan kebutuhan di sekitar, sekaligus membuka peluang usaha bagi pelaku usaha lokal.">

    {{-- The tab icon is the same mark as partials/brand.blade.php: browsers that
         understand SVG pick favicon.svg, the rest fall back to favicon.ico. --}}
    <link rel="icon" href="{{ url('/favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ url('/apple-touch-icon.png') }}">

    <title>{{ $title }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="{{ $bodyClass }}">
    @include('partials.icon-sprite')

    <div class="{{ $shellClass }}" data-app-shell data-tab="{{ $tab }}">
        {{-- Two layers, always: the tab's skeleton is painted by the server and
             sits underneath, and the real page fades in above it once it has
             data. Pressing a tab therefore shows that tab's shape immediately —
             never a blank column, never the previous page's content.

             Every tab's shape is rendered here (only the current one is shown),
             so the client can swap to another tab's skeleton in the same frame
             the tab is pressed, without a request. The extra markup is inert and
             not painted. --}}
        <div class="pointer-events-none absolute inset-0 flex min-h-0 flex-col" data-page-skeleton aria-hidden="true">
            @foreach (['peta', 'jelajahi', 'laporan', 'profil'] as $skeletonTab)
                <div @if ($skeletonTab !== $tab) hidden @endif data-skeleton-slot="{{ $skeletonTab }}" class="flex min-h-0 flex-1 flex-col">
                    @include('partials.skeleton', ['tab' => $skeletonTab])
                </div>
            @endforeach
        </div>

        <div class="flex min-h-0 flex-1 flex-col" data-page-content>
            @yield('content')
        </div>

        @include('partials.bottom-nav', ['active' => $active, 'floating' => $active === 'peta'])
    </div>

    @php
        // PR #26: the map-first home floats the panel above the nav, so the toast
        // clears it. A precomputed class keeps `@class` out of the compiled path.
        $toastClass = $active === 'peta'
            ? 'toast pointer-events-none fixed inset-x-0 bottom-[calc(13.75rem+env(safe-area-inset-bottom))] z-[1400] flex justify-center px-4 lg:bottom-24'
            : 'toast pointer-events-none fixed inset-x-0 bottom-24 z-[1400] flex justify-center px-4';
    @endphp

    <div id="toast" class="{{ $toastClass }}" role="status" aria-live="polite" aria-atomic="true">
        <div class="flex max-w-[420px] items-start gap-2.5 rounded-2xl bg-brand-800 px-4 py-3 text-[12px] font-medium leading-relaxed text-white shadow-[0_18px_40px_-18px_rgba(0,0,0,0.65)] ring-1 ring-white/10">
            @include('partials.icon', ['name' => 'info', 'class' => 'mt-0.5 size-4 shrink-0 text-brand-200'])
            <span data-toast-message></span>
        </div>
    </div>
</body>
</html>
