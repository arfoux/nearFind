<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0B0E12">
    <meta name="description" content="Masuk atau daftar akun nearFind untuk mendukung dan melaporkan kebutuhan warga di sekitarmu.">

    <link rel="icon" href="{{ url('/favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ url('/apple-touch-icon.png') }}">

    <title>@yield('title', 'Masuk atau daftar · nearFind')</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-background min-h-dvh text-ink antialiased">
    @include('partials.icon-sprite')

    <main class="relative mx-auto flex min-h-dvh w-full max-w-[460px] flex-col justify-center px-4 py-8 sm:px-6">
        @yield('content')
    </main>
</body>
</html>
