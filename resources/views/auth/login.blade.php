@extends('layouts.auth')
@section('title', 'Masuk · nearFind')
@section('content')
    <div class="flex flex-col items-center text-center">
        @include('partials.brand', ['tagline' => false])

        <h1 class="mt-6 text-[26px] font-semibold tracking-[-0.035em] text-ink">Masuk ke nearFind</h1>
        <p class="mt-2 max-w-[330px] text-[12.5px] leading-relaxed text-muted">
            Dukung dan laporkan kebutuhan warga di sekitarmu. Sesi disimpan sebagai token tersegel tanpa database.
        </p>
    </div>

    <div class="mt-6 rounded-[28px] bg-surface p-5 shadow-[0_24px_70px_-40px_rgba(0,0,0,0.55)] ring-1 ring-white/10 sm:p-6">
        @if (session('status'))
            <div class="mb-4 flex items-start gap-2.5 rounded-2xl bg-brand-50 px-3.5 py-3 text-[11.5px] font-medium text-brand-200 ring-1 ring-white/10">
                @include('partials.icon', ['name' => 'check', 'class' => 'mt-0.5 size-4 shrink-0'])
                <span>{{ session('status') }}</span>
            </div>
        @endif

        {{--
            Slot for the edge runtime. `GET /masuk` is a prerendered static page
            (see ports/login.mjs), so the `@error` blocks below cannot fire for a
            POST handled by the port: the marker below is what the port replaces
            with the one-shot error banner. It is an empty comment in the
            prerendered asset and in the `php artisan serve` render, where the
            `@error` blocks above do the work instead.
        --}}
        <!--lw-flash-->

        {{--
            `$errors` here is the kernel's ViewErrorBag (getBag/hasBag/any/count);
            a bare `$errors->first()` compiles to a call the class does not have
            (500), and even with the bag, `first()` without a key answers ''.
            The `@error` directive compiles to `$errors->getBag('default')->has()`
            /`first()`, which is the supported shape — so each field shows its own
            message.
        --}}

        <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="text-[11px] font-semibold text-brand-200">Email</label>

                <div class="relative mt-1.5">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-muted">
                        @include('partials.icon', ['name' => 'user', 'class' => 'size-4'])
                    </span>

                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                        inputmode="email"
                        placeholder="nama@contoh.id"
                        class="h-11 w-full rounded-xl border-0 bg-canvas pl-10 pr-3 text-[12.5px] text-ink ring-1 ring-white/10 transition placeholder:text-muted/70 focus:bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand-500/40"
                    >
                </div>

                @error('email')
                    <p class="mt-1 text-[10px] font-medium text-[#FF9B9B]">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="text-[11px] font-semibold text-brand-200">Kata sandi</label>

                <div class="relative mt-1.5">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-muted">
                        @include('partials.icon', ['name' => 'lock', 'class' => 'size-4'])
                    </span>

                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                        data-password-input
                        class="h-11 w-full rounded-xl border-0 bg-canvas pl-10 pr-11 text-[12.5px] text-ink ring-1 ring-white/10 transition placeholder:text-muted/70 focus:bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand-500/40"
                    >

                    <button
                        type="button"
                        data-password-toggle
                        class="absolute inset-y-0 right-1.5 my-auto flex size-8 items-center justify-center rounded-lg text-muted transition hover:bg-brand-50 hover:text-brand-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15"
                        aria-label="Tampilkan kata sandi"
                        aria-pressed="false"
                    >
                        @include('partials.icon', ['name' => 'eye', 'class' => 'size-4'])
                    </button>
                </div>

                @error('password')
                    <p class="mt-1 text-[10px] font-medium text-[#FF9B9B]">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-2.5 text-[11.5px] font-medium text-muted">
                <input
                    type="checkbox"
                    name="remember"
                    value="1"
                    @checked(old('remember'))
                    class="size-4 rounded border-white/20 accent-brand-700 focus:ring-brand-500/30"
                >
                Ingat saya selama 30 hari
            </label>

            <button
                type="submit"
                class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-700 px-4 text-[12.5px] font-semibold text-canvas shadow-[0_16px_30px_-18px_rgba(0,0,0,0.9)] transition hover:bg-brand-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20 active:scale-[0.99]"
            >
                Masuk
                @include('partials.icon', ['name' => 'arrow-right', 'class' => 'size-4'])
            </button>
        </form>

        <p class="mt-4 text-center text-[10.5px] leading-relaxed text-muted">
            Belum punya akun?
            <a href="{{ route('register') }}" class="font-semibold text-brand-200 underline-offset-2 hover:underline">Daftar</a>
        </p>
    </div>

    <a
        href="{{ route('home') }}"
        class="mx-auto mt-5 inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-[11.5px] font-semibold text-brand-200 transition hover:text-brand-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15"
    >
        @include('partials.icon', ['name' => 'map', 'class' => 'size-3.5'])
        Kembali ke peta
    </a>
@endsection
