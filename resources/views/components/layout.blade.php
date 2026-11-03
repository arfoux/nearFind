@props(['title' => 'nearFind — Peta Kebutuhan Warga', 'active' => 'peta'])

<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#F5F8F6">
    <meta name="description" content="nearFind membantu warga menemukan dan melaporkan kebutuhan di sekitar, sekaligus membuka peluang usaha bagi pelaku usaha lokal.">

    <title>{{ $title }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-background h-dvh overflow-hidden text-ink antialiased">
    <x-icon-sprite />

    <div class="relative mx-auto flex h-dvh w-full flex-col overflow-hidden bg-canvas sm:my-4 sm:h-[calc(100dvh-2rem)] sm:max-w-[600px] sm:rounded-[2.25rem] sm:shadow-[0_30px_90px_-40px_rgba(0,60,55,0.55)] sm:ring-1 sm:ring-brand-900/10 lg:my-6 lg:h-[calc(100dvh-3rem)] lg:max-w-[1180px]">
        {{ $slot }}

        <x-bottom-nav :active="$active" />
    </div>

    <div id="toast" class="toast pointer-events-none fixed inset-x-0 bottom-[calc(42dvh+4.75rem)] z-[1400] flex justify-center px-4 lg:bottom-24" role="status" aria-live="polite" aria-atomic="true">
        <div class="flex max-w-[420px] items-start gap-2.5 rounded-2xl bg-brand-800 px-4 py-3 text-[12px] font-medium leading-relaxed text-white shadow-[0_18px_40px_-18px_rgba(0,60,55,0.65)] ring-1 ring-white/10">
            <x-icon name="info" class="mt-0.5 size-4 shrink-0 text-brand-200" />
            <span data-toast-message></span>
        </div>
    </div>
</body>
</html>
