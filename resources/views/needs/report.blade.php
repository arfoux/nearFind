@extends('layouts.app')
@php $title = 'Laporan · nearFind'; $active = 'laporan'; @endphp
@section('content')
    <div class="min-h-0 flex-1 overflow-y-auto px-4 pb-6 pt-5 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl">
            <p class="text-[9px] font-semibold uppercase tracking-[0.16em] text-brand-300">Laporan warga</p>
            <h1 class="mt-1 text-xl font-semibold tracking-[-0.03em] text-ink">Laporan kebutuhan warga</h1>
            <p class="mt-1 text-[11.5px] leading-relaxed text-muted">
                Ringkasan {{ count($needs) }} kebutuhan yang dilaporkan warga di Sukajadi dan sekitarnya.
            </p>

            <dl class="mt-4 grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                <div class="rounded-2xl bg-surface px-3.5 py-3 ring-1 ring-white/10">
                    <dt class="text-[9px] font-semibold uppercase tracking-[0.14em] text-muted">Kebutuhan</dt>
                    <dd class="mt-1 text-[17px] font-semibold tracking-[-0.02em] text-ink">{{ count($needs) }}</dd>
                </div>

                <div class="rounded-2xl bg-surface px-3.5 py-3 ring-1 ring-white/10">
                    <dt class="text-[9px] font-semibold uppercase tracking-[0.14em] text-muted">Dukungan</dt>
                    <dd class="mt-1 text-[17px] font-semibold tracking-[-0.02em] text-ink">{{ $supporters }}</dd>
                </div>

                <div class="rounded-2xl bg-surface px-3.5 py-3 ring-1 ring-white/10">
                    <dt class="text-[9px] font-semibold uppercase tracking-[0.14em] text-muted">Peluang usaha</dt>
                    <dd class="mt-1 text-[17px] font-semibold tracking-[-0.02em] text-ink">{{ $opportunities }}</dd>
                </div>
            </dl>

            <section class="mt-5 rounded-[28px] bg-surface p-5 ring-1 ring-white/10 shadow-[0_22px_60px_-40px_rgba(0,0,0,0.45)]">
                <h2 class="text-[17px] font-semibold tracking-[-0.03em] text-ink">Per kategori</h2>
                <p class="mt-0.5 text-[11px] font-medium text-muted">Sebaran kebutuhan yang sudah dilaporkan.</p>

                <ul class="mt-4 space-y-3">
                    @foreach ($categories as $category)
                        @if ($category['id'] !== 'all')
                            @php
                                $total = $perCategory[$category['id']] ?? 0;
                                $width = $peak > 0 ? (int) ($total * 100 / $peak) : 0;
                            @endphp

                        <li class="flex items-center gap-3">
                            <span class="chip-icon shrink-0" style="--chip-color: {{ $category['color'] }}">
                                @include('partials.icon', ['name' => $category['icon'], 'class' => 'size-4'])
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline justify-between gap-3">
                                    <span class="truncate text-[12px] font-semibold text-ink">{{ $category['label'] }}</span>
                                    <span class="shrink-0 text-[11px] font-semibold text-muted">{{ $total }} kebutuhan</span>
                                </div>

                                <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-canvas" role="presentation">
                                    <span class="block h-full rounded-full" style="width: {{ $width }}%; background-color: {{ $category['color'] }}"></span>
                                </div>
                            </div>
                        </li>
                        @endif
                    @endforeach
                </ul>
            </section>

            <section class="mt-4 rounded-[28px] bg-surface p-5 ring-1 ring-white/10 shadow-[0_22px_60px_-40px_rgba(0,0,0,0.45)]">
                <h2 class="text-[17px] font-semibold tracking-[-0.03em] text-ink">Paling didukung</h2>
                <p class="mt-0.5 text-[11px] font-medium text-muted">Diurutkan dari jumlah dukungan warga terbanyak.</p>

                @php $rank = 0; @endphp

                <ul class="mt-3 divide-y divide-white/5">
                    @foreach ($needs as $need)
                        @php $rank = $rank + 1; @endphp

                        <li class="flex items-center gap-3 py-2.5">
                            <span class="flex size-7 shrink-0 items-center justify-center rounded-xl bg-canvas text-[11px] font-semibold text-muted">{{ $rank }}</span>

                            <a href="{{ url('/') }}?need={{ $need['id'] }}" class="min-w-0 flex-1 group">
                                <span class="block truncate text-[12.5px] font-semibold text-ink transition group-hover:text-brand-200">{{ $need['title'] }}</span>
                                <span class="mt-0.5 block truncate text-[10.5px] font-medium text-muted">
                                    {{ $need['location'] }} • {{ str_replace('.', ',', (string) $need['distance_km']) }} km
                                </span>
                            </a>

                            <span class="flex shrink-0 items-center gap-1.5 text-[11px] font-semibold text-ink">
                                @include('partials.icon', ['name' => 'heart', 'class' => 'size-3.5 shrink-0 text-[#FB7185]'])
                                {{ $need['supporters'] }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>

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
