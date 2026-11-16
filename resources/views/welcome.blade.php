<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>nearFind: Peta Kebutuhan Warga</title>

        @fonts

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-dvh bg-canvas text-ink">
        <main class="mx-auto flex min-h-dvh w-full max-w-md flex-col justify-center px-5 py-10">
            <h1 class="text-[26px] font-semibold tracking-[-0.035em]">nearFind</h1>

            <p class="mt-2 text-[13px] leading-relaxed text-muted">
                Peta kebutuhan jasa dan fasilitas warga di Sukajadi dan sekitarnya. Lihat titiknya, lalu ajukan kebutuhan atau tambahkan tempat.
            </p>

            <div class="mt-6 flex flex-col gap-2.5">
                <a
                    href="{{ route('home') }}"
                    class="inline-flex h-12 items-center justify-center rounded-xl bg-brand-700 px-4 text-[12.5px] font-semibold text-white transition hover:bg-brand-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20"
                >
                    Buka peta
                </a>

                <a
                    href="{{ route('login') }}"
                    class="inline-flex h-12 items-center justify-center rounded-xl bg-surface px-4 text-[12.5px] font-semibold text-brand-200 ring-1 ring-ink/10 transition hover:bg-surface-2 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20"
                >
                    Masuk
                </a>
            </div>

            <p class="mt-5 text-[10.5px] leading-relaxed text-muted">
                Masuk memakai kredensial tersegel yang disiapkan untuk deployment ini. Tidak ada pendaftaran atau verifikasi email.
            </p>
        </main>
        <footer class="sr-only">nearFind, peta kebutuhan warga.</footer>
    </body>
</html>
