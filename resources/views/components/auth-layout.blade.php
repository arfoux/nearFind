@props(['title' => 'Masuk · nearFind'])

<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#F5F8F6">
    <meta name="description" content="Masuk ke nearFind untuk mendukung dan melaporkan kebutuhan warga di sekitarmu.">

    <title>{{ $title }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-background min-h-dvh text-ink antialiased">
    <x-icon-sprite />

    <main class="relative mx-auto flex min-h-dvh w-full max-w-[460px] flex-col justify-center px-4 py-8 sm:px-6">
        {{ $slot }}
    </main>
</body>
</html>
