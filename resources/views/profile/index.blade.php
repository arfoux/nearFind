@extends('layouts.app')
@php $title = 'Profil · nearFind'; $active = 'profil'; @endphp

@section('content')
    <div class="min-h-0 flex-1 overflow-y-auto px-4 pb-8 pt-5 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-[860px] pb-24">
            {{-- Identity --}}
            <section class="nf-card relative overflow-hidden rounded-[26px] p-5 sm:p-6">
                <div class="pointer-events-none absolute -right-16 -top-24 size-56 rounded-full bg-brand-500/20 blur-3xl"></div>

                <div class="relative flex flex-wrap items-start gap-4">
                    <span class="flex size-16 shrink-0 items-center justify-center rounded-[22px] bg-brand-700 text-xl font-semibold text-canvas shadow-[0_16px_32px_-20px_rgba(0,0,0,0.95)]">
                        {{ mb_strtoupper(mb_substr($profile->name, 0, 1)) }}
                    </span>

                    <div class="min-w-0 basis-[min(100%,16rem)] flex-1">
                        <p class="text-[9px] font-semibold uppercase tracking-[0.18em] text-brand-300">Akun nearFind</p>
                        <h1 class="mt-1 text-[22px] font-semibold leading-[1.15] tracking-[-0.035em] text-ink sm:text-2xl">{{ $profile->name }}</h1>
                        <p class="mt-1 truncate text-[11.5px] text-muted">
                            {{ $email }}
                            @if ($profile->username)
                                <span class="text-muted/60">•</span>
                                {{ '@'.$profile->username }}
                            @endif
                        </p>

                        <div class="mt-3 flex flex-wrap items-center gap-1.5">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-2.5 py-1 text-[9.5px] font-semibold text-brand-200 ring-1 ring-white/10">
                                @include('partials.icon', ['name' => 'shield-check', 'class' => 'size-3'])
                                Sesi bertanda tangan
                            </span>

                            <span class="inline-flex items-center gap-1.5 rounded-full bg-canvas px-2.5 py-1 text-[9.5px] font-semibold text-muted ring-1 ring-white/10">
                                @include('partials.icon', ['name' => 'mail', 'class' => 'size-3'])
                                {{ $email }}
                            </span>
                        </div>
                    </div>

                    <a
                        href="{{ route('home') }}"
                        data-app-link
                        class="inline-flex h-9 items-center gap-1.5 rounded-full bg-surface-2 px-3.5 text-[11px] font-semibold text-brand-200 ring-1 ring-white/10 transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15"
                    >
                        @include('partials.icon', ['name' => 'map', 'class' => 'size-3.5'])
                        ke peta
                    </a>
                </div>

                <dl class="relative mt-5 grid grid-cols-2 gap-2.5">
                    @foreach ([
                        ['label' => 'Diajukan', 'value' => $stats['submitted']],
                        ['label' => 'Didukung', 'value' => $stats['supported']],
                    ] as $stat)
                        <div class="rounded-2xl bg-canvas px-3.5 py-3 ring-1 ring-white/5">
                            <dt class="text-[9px] font-semibold uppercase tracking-[0.14em] text-muted">{{ $stat['label'] }}</dt>
                            <dd class="mt-1 text-[18px] font-semibold tracking-[-0.03em] text-ink">{{ $stat['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            @if (session('status'))
                <div class="nf-card mt-3 flex items-start gap-2.5 rounded-2xl px-3.5 py-3 text-[11.5px] font-medium text-brand-200" role="status">
                    @include('partials.icon', ['name' => 'check', 'class' => 'mt-0.5 size-4 shrink-0 text-brand-300'])
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            {{-- Submitted --}}
            <section class="mt-4" aria-labelledby="submitted-title">
                <div class="mb-2 flex items-center justify-between gap-3 px-1">
                    <h2 id="submitted-title" class="text-[11px] font-semibold uppercase tracking-[0.14em] text-brand-200">Kebutuhan yang kamu ajukan</h2>
                </div>

                @if (count($submittedNeeds) === 0)
                    <div class="nf-card rounded-[24px] p-5 text-[11.5px] leading-relaxed text-muted">
                        Belum ada. Tekan tombol tambah di peta untuk mengajukan kebutuhan pertamamu.
                    </div>
                @else
                    <ul class="grid gap-2.5" role="list">
                        @foreach ($submittedNeeds as $need)
                            <li class="nf-card rounded-[22px] p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-[9px] font-semibold uppercase tracking-[0.14em] text-brand-300">{{ $need['category'] }}</p>
                                        <h3 class="mt-1 truncate text-[13.5px] font-semibold tracking-[-0.02em] text-ink">{{ $need['title'] }}</h3>

                                        @if ($need['location'] !== '')
                                            <p class="mt-1 flex items-center gap-1.5 text-[10.5px] text-muted">
                                                @include('partials.icon', ['name' => 'pin', 'class' => 'size-3 shrink-0'])
                                                {{ $need['location'] }}
                                            </p>
                                        @endif
                                    </div>

                                    <span class="shrink-0 rounded-full bg-brand-50 px-2.5 py-1 text-[9px] font-semibold uppercase tracking-[0.1em] text-brand-200">{{ $need['supporters'] }} dukungan</span>
                                </div>

                                @if ($need['description'] !== '')
                                    <p class="mt-2 line-clamp-2 text-[11px] leading-relaxed text-muted">{{ $need['description'] }}</p>
                                @endif

                                <form method="POST" action="{{ route('needs.destroy', $need['id']) }}" class="mt-3" onsubmit="return confirm('Hapus pengajuan “{{ $need['title'] }}”? Tindakan ini tidak bisa dibatalkan.');">
                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-canvas px-2.5 py-1 text-[10px] font-semibold text-[#FF9B9B] ring-1 ring-white/10 transition hover:bg-[#FF9B9B]/10 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF9B9B]/20"
                                    >
                                        @include('partials.icon', ['name' => 'close', 'class' => 'size-3'])
                                        Batalkan pengajuan
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Supported --}}
            <section class="mt-4" aria-labelledby="supported-title">
                <div class="mb-2 flex items-center justify-between gap-3 px-1">
                    <h2 id="supported-title" class="text-[11px] font-semibold uppercase tracking-[0.14em] text-brand-200">Kebutuhan yang kamu dukung</h2>
                </div>

                @if (count($supportedNeeds) === 0)
                    <div class="nf-card rounded-[24px] p-5 text-[11.5px] leading-relaxed text-muted">
                        Belum ada. Dukungan tercatat setelah kamu menekan “Saya juga butuh” di sebuah kebutuhan.
                    </div>
                @else
                    <ul class="grid gap-2.5 sm:grid-cols-2" role="list">
                        @foreach ($supportedNeeds as $need)
                            <li class="nf-card rounded-[22px] p-4">
                                <h3 class="truncate text-[12.5px] font-semibold text-ink">{{ $need['title'] }}</h3>
                                <p class="mt-1 text-[10.5px] text-muted">{{ $need['supporters'] }} warga mendukung</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <form method="POST" action="{{ route('logout') }}" class="mt-6">
                @csrf

                <button
                    type="submit"
                    class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-[#3A1518] px-4 text-[12px] font-semibold text-[#FF9B9B] ring-1 ring-[#FF9B9B]/15 transition hover:brightness-110 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF9B9B]/20"
                >
                    @include('partials.icon', ['name' => 'log-out', 'class' => 'size-4'])
                    Keluar dari akun
                </button>
            </form>
        </div>
    </div>
@endsection
