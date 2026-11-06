@php
    $title = $title ?? 'nearFind — Peta Kebutuhan Warga';
    $active = $active ?? 'peta';

    // The map page IS the canvas: the MapLibre surface runs edge to edge under a
    // full-bleed shell and every control floats on glass above it. No framed
    // card, no page background behind it. Every other page keeps the card.
    $isMap = $active === 'peta';
    $bodyClass = $isMap
        ? 'h-dvh overflow-hidden bg-transparent text-ink antialiased'
        : 'app-background h-dvh overflow-hidden text-ink antialiased';
    $shellClass = $isMap
        ? 'relative mx-auto flex h-dvh w-full flex-col overflow-hidden'
        : 'relative mx-auto flex h-dvh w-full flex-col overflow-hidden bg-canvas sm:my-4 sm:h-[calc(100dvh-2rem)] sm:max-w-[600px] sm:rounded-[2.25rem] sm:shadow-[0_30px_90px_-40px_rgba(0,0,0,0.55)] sm:ring-1 sm:ring-white/10 lg:my-6 lg:h-[calc(100dvh-3rem)] lg:max-w-[1180px]';
@endphp

<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0B0E12">
    <meta name="description" content="nearFind membantu warga menemukan dan melaporkan kebutuhan di sekitar, sekaligus membuka peluang usaha bagi pelaku usaha lokal.">

    {{-- The tab icon is the same mark as partials/brand.blade.php; favicon.ico is
         a 0-byte placeholder, so without this the tab shows nothing. --}}
    <link rel="icon" href="{{ url('/favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ url('/apple-touch-icon.png') }}">

    <title>{{ $title }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="{{ $bodyClass }}">
    @include('partials.icon-sprite')

    <div class="{{ $shellClass }}">
        @yield('content')

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
