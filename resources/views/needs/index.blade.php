@extends('layouts.app')
@php $title = 'Jelajahi · nearFind'; $active = 'jelajahi'; @endphp
@section('content')
    <div class="min-h-0 flex-1 overflow-y-auto px-4 pb-6 pt-5 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl">
            <p class="text-[9px] font-semibold uppercase tracking-[0.16em] text-brand-300">Jelajahi</p>
            <h1 class="mt-1 text-xl font-semibold tracking-[-0.03em] text-ink">Kebutuhan di sekitarmu</h1>
            <p class="mt-1 text-[11.5px] leading-relaxed text-muted">
                {{ count($needs) }} kebutuhan warga di Sukajadi dan sekitarnya. Cari, saring per kategori, lalu buka di peta.
            </p>

            <div class="relative mt-4">
                <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-muted">
                    @include('partials.icon', ['name' => 'search', 'class' => 'size-4'])
                </span>

                <input
                    type="search"
                    data-list-search
                    autocomplete="off"
                    placeholder="Cari kebutuhan, lokasi, atau kata kunci"
                    aria-label="Cari kebutuhan"
                    class="h-11 w-full rounded-2xl bg-surface pl-10 pr-11 text-[12.5px] font-medium text-ink ring-1 ring-white/10 transition placeholder:text-muted/70 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20"
                >

                <button
                    type="button"
                    data-list-clear
                    hidden
                    aria-label="Hapus kata kunci"
                    class="absolute inset-y-0 right-2.5 my-auto flex size-8 items-center justify-center rounded-xl text-muted transition hover:bg-brand-50 hover:text-brand-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20"
                >
                    @include('partials.icon', ['name' => 'close', 'class' => 'size-4'])
                </button>
            </div>

            <div class="no-scrollbar mt-3 flex gap-2 overflow-x-auto pb-1" role="group" aria-label="Saring kategori kebutuhan">
                @foreach ($categories as $category)
                    <button
                        type="button"
                        data-list-category="{{ $category['id'] }}"
                        aria-pressed="{{ $category['id'] === 'all' ? 'true' : 'false' }}"
                        class="category-chip {{ $category['id'] === 'all' ? 'is-active' : '' }}"
                    >
                        @if ($category['id'] !== 'all')
                            <span class="chip-icon" style="--chip-color: {{ $category['color'] }}">
                                @include('partials.icon', ['name' => $category['icon'], 'class' => 'size-3.5'])
                            </span>
                        @endif

                        <span>{{ $category['label'] }}</span>
                    </button>
                @endforeach
            </div>

            <div data-needs-list class="mt-4 space-y-2.5">
                @foreach ($needs as $need)
                    @php
                        $category = null;

                        foreach ($categories as $candidate) {
                            if ($candidate['id'] === $need['category']) {
                                $category = $candidate;

                                break;
                            }
                        }

                        $searchText = $need['title'].' '.$need['location'].' '.$need['area'].' '.$need['description'].' '.($category['label'] ?? '');
                    @endphp

                    <article
                        data-need-row
                        data-category="{{ $need['category'] }}"
                        data-search="{{ strtolower($searchText) }}"
                        class="rounded-[24px] bg-surface p-4 ring-1 ring-white/10 shadow-[0_18px_45px_-38px_rgba(0,0,0,0.9)]"
                    >
                        <div class="flex items-start gap-3">
                            <span class="chip-icon mt-0.5" style="--chip-color: {{ $category['color'] ?? '#A78BFA' }}">
                                @include('partials.icon', ['name' => $category['icon'] ?? 'grid', 'class' => 'size-4'])
                            </span>

                            <div class="min-w-0 flex-1">
                                <h2 class="truncate text-[13.5px] font-semibold tracking-[-0.02em] text-ink">{{ $need['title'] }}</h2>
                                <p class="mt-0.5 flex items-center gap-1.5 text-[11px] font-medium text-muted">
                                    @include('partials.icon', ['name' => 'pin', 'class' => 'size-3.5 shrink-0 text-brand-300'])
                                    <span class="truncate">
                                        {{ $need['location'] }} <span class="text-brand-200">• {{ str_replace('.', ',', (string) $need['distance_km']) }} km</span>
                                    </span>
                                </p>
                            </div>

                            @if (! empty($need['opportunity']))
                                <span class="shrink-0 rounded-lg bg-brand-50 px-2 py-1 text-[8.5px] font-semibold uppercase tracking-[0.1em] text-brand-200">
                                    Peluang usaha
                                </span>
                            @endif
                        </div>

                        <p class="mt-2.5 line-clamp-2 text-[12px] leading-[1.6] text-muted">{{ $need['description'] }}</p>

                        <div class="mt-3 flex items-center justify-between gap-3">
                            <p class="flex items-center gap-1.5 text-[11px] font-semibold text-ink">
                                @include('partials.icon', ['name' => 'heart', 'class' => 'size-3.5 shrink-0 text-[#FB7185]'])
                                {{ $need['supporters'] }} warga mendukung
                            </p>

                            <a
                                href="{{ url('/') }}?need={{ $need['id'] }}"
                                class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-brand-50 px-3 text-[11px] font-semibold text-brand-200 ring-1 ring-white/10 transition hover:bg-brand-100 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20"
                            >
                                @include('partials.icon', ['name' => 'map', 'class' => 'size-3.5'])
                                Lihat di peta
                            </a>
                        </div>
                    </article>
                @endforeach

                <div data-list-empty hidden class="rounded-[24px] bg-surface px-4 py-10 text-center ring-1 ring-white/10">
                    <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-200">
                        @include('partials.icon', ['name' => 'search', 'class' => 'size-7'])
                    </span>

                    <h2 class="mt-4 text-[16px] font-semibold tracking-[-0.02em] text-ink">Belum ada yang cocok</h2>
                    <p class="mx-auto mt-1.5 max-w-[280px] text-[11.5px] leading-relaxed text-muted">
                        Coba kata kunci lain atau tampilkan kembali semua kategori.
                    </p>
                </div>
            </div>

            <a
                href="{{ route('home') }}"
                class="mt-4 inline-flex items-center gap-1.5 rounded-lg px-1 py-1 text-[11.5px] font-semibold text-brand-200 transition hover:text-brand-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15"
            >
                @include('partials.icon', ['name' => 'map', 'class' => 'size-3.5'])
                Kembali ke peta
            </a>
        </div>
    </div>
@endsection
