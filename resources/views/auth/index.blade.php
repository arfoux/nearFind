@extends('layouts.auth')
@section('title', 'Masuk atau daftar · nearFind')

@php
    // A register POST that comes back with validation errors has `name` in the
    // old input, so the page reopens on the register tab even without `?mode`.
    $initialMode = ($initialMode ?? 'login') === 'register' || old('name') !== null ? 'register' : 'login';

    // Where to go after signing in: the page the auth middleware sent this guest
    // from (`/auth?redirect=/profile`), echoed back by both forms.
    $redirect = (string) (old('redirect') ?: request()->query('redirect', ''));
    $registerUrl = route('login', ['mode' => 'register'])
        .($redirect !== '' ? '&redirect='.urlencode($redirect) : '');
@endphp

@section('content')
    <div class="flex flex-col items-center text-center">
        @include('partials.brand', ['tagline' => false])

        <h1 class="mt-6 text-[26px] font-semibold tracking-[-0.035em] text-ink">Masuk atau daftar</h1>
        <p class="mt-2 max-w-[340px] text-[12.5px] leading-relaxed text-muted">
            Dukung dan laporkan kebutuhan warga di sekitarmu. Satu akun untuk masuk, melapor, dan memantau.
        </p>
    </div>

    <div
        class="mt-6 rounded-[28px] bg-surface p-3 shadow-[0_24px_70px_-40px_rgba(0,0,0,0.55)] ring-1 ring-white/10 sm:p-4"
        data-auth
        data-auth-initial="{{ $initialMode }}"
    >
        @if (session('status'))
            <div class="mb-3 flex items-start gap-2.5 rounded-2xl bg-brand-50 px-3.5 py-3 text-[11.5px] font-medium text-brand-200 ring-1 ring-white/10">
                @include('partials.icon', ['name' => 'check', 'class' => 'mt-0.5 size-4 shrink-0'])
                <span>{{ session('status') }}</span>
            </div>
        @endif

        {{--
            Slot for the edge runtime. `GET /auth` is a prerendered static page
            (see ports/login.mjs), so the `@error` blocks below cannot fire for a
            POST handled by the port: the marker below is what the port replaces
            with the one-shot error banner. It is an empty comment in the
            prerendered asset and in the `php artisan serve` render, where the
            `@error` blocks above do the work instead.
        --}}
        <!--lw-flash-->

        <div role="tablist" aria-label="Masuk atau daftar" class="grid grid-cols-2 gap-1 rounded-2xl bg-canvas p-1">
            <a
                href="{{ route('login') }}"
                role="tab"
                id="auth-tab-login"
                aria-controls="auth-panel-login"
                aria-selected="{{ $initialMode === 'login' ? 'true' : 'false' }}"
                tabindex="{{ $initialMode === 'login' ? '0' : '-1' }}"
                data-auth-tab="login"
                class="inline-flex h-10 items-center justify-center rounded-xl px-3 text-[12px] font-semibold text-muted transition hover:text-brand-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20 aria-selected:bg-surface aria-selected:text-ink aria-selected:shadow-sm aria-selected:ring-1 aria-selected:ring-white/10"
            >
                Masuk
            </a>

            <a
                href="{{ $registerUrl }}"
                role="tab"
                id="auth-tab-register"
                aria-controls="auth-panel-register"
                aria-selected="{{ $initialMode === 'register' ? 'true' : 'false' }}"
                tabindex="{{ $initialMode === 'register' ? '0' : '-1' }}"
                data-auth-tab="register"
                class="inline-flex h-10 items-center justify-center rounded-xl px-3 text-[12px] font-semibold text-muted transition hover:text-brand-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20 aria-selected:bg-surface aria-selected:text-ink aria-selected:shadow-sm aria-selected:ring-1 aria-selected:ring-white/10"
            >
                Daftar
            </a>
        </div>

        <div class="p-1 pt-4 sm:p-2 sm:pt-4">
            <section
                role="tabpanel"
                id="auth-panel-login"
                aria-labelledby="auth-tab-login"
                data-auth-panel="login"
                @if ($initialMode !== 'login') hidden @endif
                class="focus:outline-none"
            >
                <form method="POST" action="{{ route('auth.login') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="redirect" value="{{ $redirect }}">

                    <div>
                        <label for="login-email" class="text-[11px] font-semibold text-brand-200">Email</label>

                        <div class="relative mt-1.5">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-muted">
                                @include('partials.icon', ['name' => 'user', 'class' => 'size-4'])
                            </span>

                            <input
                                id="login-email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                required
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
                        <label for="login-password" class="text-[11px] font-semibold text-brand-200">Kata sandi</label>

                        <div class="relative mt-1.5">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-muted">
                                @include('partials.icon', ['name' => 'lock', 'class' => 'size-4'])
                            </span>

                            <input
                                id="login-password"
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

                    <label for="login-remember" class="flex items-center gap-2.5 text-[11.5px] font-medium text-muted">
                        <input
                            id="login-remember"
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
                    <a
                        href="{{ $registerUrl }}"
                        data-auth-tab="register"
                        class="font-semibold text-brand-200 underline-offset-2 hover:underline focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15"
                    >
                        Daftar
                    </a>
                </p>
            </section>

            <section
                role="tabpanel"
                id="auth-panel-register"
                aria-labelledby="auth-tab-register"
                data-auth-panel="register"
                @if ($initialMode !== 'register') hidden @endif
                class="focus:outline-none"
            >
                <p class="mb-4 flex items-start gap-2.5 rounded-2xl bg-canvas px-3.5 py-3 text-[10.5px] leading-relaxed text-muted ring-1 ring-white/5">
                    @include('partials.icon', ['name' => 'info', 'class' => 'mt-0.5 size-4 shrink-0 text-brand-300'])
                    <span>Kami kirim tautan verifikasi ke emailmu. Akun aktif setelah tautan itu dibuka.</span>
                </p>

                <form method="POST" action="{{ route('auth.register') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="redirect" value="{{ $redirect }}">

                    <div>
                        <label for="register-name" class="text-[11px] font-semibold text-brand-200">Nama</label>

                        <div class="relative mt-1.5">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-muted">
                                @include('partials.icon', ['name' => 'user', 'class' => 'size-4'])
                            </span>

                            <input
                                id="register-name"
                                name="name"
                                type="text"
                                value="{{ old('name') }}"
                                required
                                autocomplete="name"
                                maxlength="60"
                                placeholder="Nama panggilanmu"
                                class="h-11 w-full rounded-xl border-0 bg-canvas pl-10 pr-3 text-[12.5px] text-ink ring-1 ring-white/10 transition placeholder:text-muted/70 focus:bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand-500/40"
                            >
                        </div>

                        @error('name')
                            <p class="mt-1 text-[10px] font-medium text-[#FF9B9B]">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="register-email" class="text-[11px] font-semibold text-brand-200">Email</label>

                        <div class="relative mt-1.5">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-muted">
                                @include('partials.icon', ['name' => 'user', 'class' => 'size-4'])
                            </span>

                            <input
                                id="register-email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                required
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
                        <label for="register-password" class="text-[11px] font-semibold text-brand-200">Kata sandi</label>

                        <div class="relative mt-1.5">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-muted">
                                @include('partials.icon', ['name' => 'lock', 'class' => 'size-4'])
                            </span>

                            <input
                                id="register-password"
                                name="password"
                                type="password"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="Minimal 8 karakter"
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

                    <div>
                        <label for="register-password-confirmation" class="text-[11px] font-semibold text-brand-200">Ulangi kata sandi</label>

                        <div class="relative mt-1.5">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-muted">
                                @include('partials.icon', ['name' => 'lock', 'class' => 'size-4'])
                            </span>

                            <input
                                id="register-password-confirmation"
                                name="password_confirmation"
                                type="password"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="••••••••"
                                class="h-11 w-full rounded-xl border-0 bg-canvas pl-10 pr-3 text-[12.5px] text-ink ring-1 ring-white/10 transition placeholder:text-muted/70 focus:bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand-500/40"
                            >
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-700 px-4 text-[12.5px] font-semibold text-canvas shadow-[0_16px_30px_-18px_rgba(0,0,0,0.9)] transition hover:bg-brand-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20 active:scale-[0.99]"
                    >
                        Daftar
                        @include('partials.icon', ['name' => 'arrow-right', 'class' => 'size-4'])
                    </button>
                </form>

                <p class="mt-4 text-center text-[10.5px] leading-relaxed text-muted">
                    Sudah punya akun?
                    <a
                        href="{{ route('login') }}"
                        data-auth-tab="login"
                        class="font-semibold text-brand-200 underline-offset-2 hover:underline focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15"
                    >
                        Masuk
                    </a>
                </p>
            </section>
        </div>
    </div>

    <a
        href="{{ route('home') }}"
        class="mx-auto mt-5 inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-[11.5px] font-semibold text-brand-200 transition hover:text-brand-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15"
    >
        @include('partials.icon', ['name' => 'map', 'class' => 'size-3.5'])
        Kembali ke peta
    </a>
@endsection
