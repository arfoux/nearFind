<!DOCTYPE html>
<html lang="id" class="h-full" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#F7F5F1">
    {{-- Pre-paint theme: no external script, no flash of the wrong palette. --}}
    {{-- R-31: light is the calm first-visit default for a civic map; a stored nf-theme choice always wins. --}}
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('nf-theme');
                var theme = saved === 'light' || saved === 'dark' ? saved : 'light';
                document.documentElement.setAttribute('data-theme', theme);
                var meta = document.querySelector('meta[name="theme-color"]');
                if (meta) meta.setAttribute('content', theme === 'light' ? '#F7F5F1' : '#0B0E12');
            } catch (error) {
                /* Storage can be blocked; the light default on <html> still applies. */
            }
        })();
    </script>
    <meta name="description" content="Masuk ke nearFind untuk mendukung dan melaporkan kebutuhan warga di sekitarmu.">

    <link rel="icon" href="{{ url('/favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ url('/apple-touch-icon.png') }}">

    <title>@yield('title', 'Masuk · nearFind')</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-canvas text-ink antialiased">
    @include('partials.icon-sprite')

    <button
        type="button"
        class="nf-theme-toggle nf-theme-toggle--auth"
        data-theme-toggle
        aria-pressed="false"
        aria-label="Ganti tema"
        title="Ganti tema"
    >
        @include('partials.icon', ['name' => 'sun', 'class' => 'nf-theme-icon nf-theme-icon--sun size-5'])
        @include('partials.icon', ['name' => 'moon', 'class' => 'nf-theme-icon nf-theme-icon--moon size-5'])
    </button>

    <main class="relative mx-auto flex min-h-dvh w-full max-w-[460px] flex-col justify-center px-4 py-8 sm:px-6">
        @yield('content')
    </main>
    <footer class="sr-only">nearFind, peta kebutuhan warga.</footer>
</body>
</html>
