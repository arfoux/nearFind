@extends('layouts.auth')
@section('title', 'Masuk · nearFind')

@php
    // The route a guest was heading to, stashed by the auth middleware. The
    // hidden field echoes it back so signing in returns to that page.
    $redirect = (string) (old('redirect') ?: request()->query('redirect', ''));
@endphp

@section('content')
    <div class="flex flex-col items-center text-center">
        @include('partials.brand', ['tagline' => false])

        <h1 class="mt-6 text-[26px] font-semibold tracking-[-0.035em] text-ink">Masuk ke nearFind</h1>
        <p class="mt-2 max-w-[340px] text-[12.5px] leading-relaxed text-muted">
            Masuk memakai kredensial tersegel yang disiapkan untuk deployment ini. Tidak ada pendaftaran atau verifikasi email.
        </p>
    </div>

    <div
        class="mt-6 rounded-[28px] bg-surface p-3 shadow-[0_24px_70px_-40px_rgba(0,0,0,0.55)] ring-1 ring-ink/10 sm:p-4"
        data-auth
        data-auth-initial="login"
    >
        @if (session('status'))
            <div class="mb-3 flex items-start gap-2.5 rounded-2xl bg-brand-50 px-3.5 py-3 text-[11.5px] font-medium text-brand-200 ring-1 ring-ink/10" role="status">
                @include('partials.icon', ['name' => 'check', 'class' => 'mt-0.5 size-4 shrink-0'])
                <span>{{ session('status') }}</span>
            </div>
        @endif

        {{-- Slot for the edge runtime. `GET /auth` is a prerendered static page
             (see ports/login.mjs), so the `@error` blocks below cannot fire for
             a POST handled by the port: the marker below is what the port
             replaces with the one-shot error banner. --}}
        <!--lw-flash-->

        <div class="p-1 pt-2 sm:p-2 sm:pt-2">
            <section id="auth-panel-login" data-auth-panel="login" class="focus:outline-none">
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
                            <p class="mt-1 text-[10px] font-medium text-danger">{{ $message }}</p>
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
                                class="h-11 w-full rounded-xl border-0 bg-canvas pl-10 pr-14 text-[12.5px] text-ink ring-1 ring-white/10 transition placeholder:text-muted/70 focus:bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand-500/40"
                            >

                            <button
                                type="button"
                                data-password-toggle
                                class="absolute inset-y-0 right-1.5 my-auto flex size-11 items-center justify-center rounded-lg text-muted transition hover:bg-brand-50 hover:text-brand-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15"
                                aria-label="Tampilkan kata sandi"
                                aria-pressed="false"
                            >
                                @include('partials.icon', ['name' => 'eye', 'class' => 'size-4'])
                            </button>
                        </div>

                        @error('password')
                            <p class="mt-1 text-[10px] font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <label for="login-remember" class="flex min-h-11 items-center gap-2.5 text-[11.5px] font-medium text-muted">
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
                        class="inline-flex h-11 w-full items-center justify-center rounded-xl bg-brand-700 px-4 text-[12.5px] font-semibold text-white shadow-[0_16px_30px_-18px_rgba(0,0,0,0.9)] transition hover:bg-brand-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20 active:scale-[0.99]"
                    >
                        Masuk
                    </button>
                </form>
            </section>
        </div>
    </div>

    <a
        href="{{ route('home') }}"
        class="mx-auto mt-5 inline-flex min-h-11 items-center gap-1.5 rounded-lg px-2 text-[11.5px] font-semibold text-brand-200 transition hover:text-brand-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15"
    >
        @include('partials.icon', ['name' => 'map', 'class' => 'size-3.5'])
        Kembali ke peta
    </a>
@endsection
