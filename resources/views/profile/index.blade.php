@extends('layouts.app')
@php $title = 'Profil - nearFind'; $active = 'profil'; @endphp
@php
    $formatDate = fn ($date) => $date ? $date->copy()->locale('id')->translatedFormat('j M Y') : '-';

    $needStatusStyles = [
        'aktif' => 'bg-brand-50 text-brand-200 ring-white/10',
        'selesai' => 'bg-brand-50 text-brand-200 ring-brand-500/15',
        'ditutup' => 'bg-canvas text-muted ring-white/10',
    ];

    $reportStatusStyles = [
        'diproses' => 'bg-accent-50 text-accent-600 ring-accent-500/15',
        'diterima' => 'bg-brand-50 text-brand-200 ring-white/10',
        'selesai' => 'bg-brand-50 text-brand-200 ring-brand-500/15',
        'ditolak' => 'bg-[#3A1518] text-[#FF9B9B] ring-[#FF9B9B]/15',
    ];

    $settingsItems = [
        ['modal' => 'edit-profile', 'icon' => 'pencil', 'title' => 'Informasi Pribadi', 'description' => 'Nama lengkap, username, telepon, dan bio'],
        ['modal' => 'password', 'icon' => 'lock', 'title' => 'Ubah Password', 'description' => 'Perbarui kata sandi akunmu'],
        ['modal' => 'notifications', 'icon' => 'bell', 'title' => 'Pengaturan Notifikasi', 'description' => 'Atur kabar aktivitas dan rangkuman mingguan'],
        ['modal' => 'help', 'icon' => 'help', 'title' => 'Bantuan', 'description' => 'Panduan singkat dan kontak pengurus RW'],
        ['modal' => 'logout', 'icon' => 'log-out', 'title' => 'Logout', 'description' => 'Keluar dari akun nearFind', 'danger' => true],
    ];

    $initialModal = old('_form', session('open_modal'));
@endphp

@section('content')
    <div class="relative flex min-h-0 flex-1 flex-col">
        <header class="relative z-[700] shrink-0 bg-canvas px-4 pb-2 pt-3.5 sm:px-5 lg:px-6 lg:pb-3 lg:pt-5">
            <div class="flex items-center justify-between gap-3">
                @include('partials.brand')

                <a
                    href="{{ route('home') }}"
                    data-app-link
                    class="inline-flex h-9 items-center gap-1.5 rounded-full bg-surface px-3.5 text-[11px] font-semibold text-brand-200 ring-1 ring-white/10 transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15"
                >
                    @include('partials.icon', ['name' => 'map', 'class' => 'size-3.5'])
                    Kembali ke peta
                </a>
            </div>
        </header>

        <main class="scrollbar-card min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 pb-8 pt-1 sm:px-5 lg:px-6">
            <div class="mx-auto w-full max-w-[860px]" data-profile-page data-initial-modal="{{ $initialModal }}">
                @if (session('status'))
                    <div class="mb-3 flex items-start gap-2.5 rounded-2xl bg-surface px-3.5 py-3 text-[11.5px] font-medium text-brand-200 shadow-[0_12px_30px_-24px_rgba(0,0,0,0.6)] ring-1 ring-white/10" role="status">
                        @include('partials.icon', ['name' => 'check', 'class' => 'mt-0.5 size-4 shrink-0 text-brand-300'])
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                <section class="relative overflow-hidden rounded-[28px] bg-brand-800 text-white shadow-[0_26px_70px_-42px_rgba(0,0,0,0.8)] ring-1 ring-white/10">
                    <div class="pointer-events-none absolute -right-16 -top-20 size-56 rounded-full bg-accent-500/20 blur-2xl"></div>
                    <div class="pointer-events-none absolute -bottom-24 left-10 size-52 rounded-full bg-brand-500/30 blur-2xl"></div>

                    <div class="relative p-5 sm:p-6">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-start gap-4">
                                {{-- The default avatar: one of six palettes, picked from the row id. --}}
                                <div class="nf-avatar nf-avatar--{{ $profile->avatar_variant }} relative flex size-20 shrink-0 items-center justify-center rounded-[26px] text-2xl font-semibold tracking-[-0.04em] text-white ring-4 ring-white/15 sm:size-24">
                                    {{ $profile->initials }}
                                </div>

                                <div class="min-w-0 pt-1">
                                    <p class="text-[9px] font-semibold uppercase tracking-[0.18em] text-brand-200">Akun nearFind</p>
                                    <h1 class="mt-1 truncate text-[23px] font-semibold leading-tight tracking-[-0.035em] sm:text-[27px]">{{ $profile->name }}</h1>
                                    <p class="mt-0.5 truncate text-[11.5px] font-medium text-brand-200">{{ $profile->display_username }}</p>

                                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                        <span class="inline-flex items-center gap-1 rounded-full bg-white/10 px-2.5 py-1 text-[9.5px] font-semibold text-white ring-1 ring-white/15">
                                            @include('partials.icon', ['name' => 'shield-check', 'class' => 'size-3'])
                                            Akun aktif
                                        </span>

                                        <span class="inline-flex min-w-0 items-center gap-1 rounded-full bg-white/10 px-2.5 py-1 text-[9.5px] font-semibold text-white ring-1 ring-white/15">
                                            @include('partials.icon', ['name' => 'mail', 'class' => 'size-3 shrink-0'])
                                            <span class="truncate">{{ $profile->email }}</span>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <button
                                type="button"
                                data-open-modal="edit-profile"
                                class="hidden h-9 shrink-0 items-center gap-1.5 rounded-xl bg-surface px-3.5 text-[11px] font-semibold text-brand-200 shadow-[0_12px_24px_-18px_rgba(0,0,0,0.6)] transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/25 sm:inline-flex"
                            >
                                @include('partials.icon', ['name' => 'pencil', 'class' => 'size-3.5'])
                                Edit Profil
                            </button>
                        </div>

                        @if ($profile->bio)
                            <p class="mt-4 max-w-[620px] text-[12px] leading-relaxed text-brand-200">{{ $profile->bio }}</p>
                        @else
                            <p class="mt-4 max-w-[620px] text-[12px] leading-relaxed text-brand-200/80">Tambahkan bio singkat supaya orang lain mengenalmu.</p>
                        @endif

                        <div class="mt-4 grid gap-2 sm:grid-cols-2">
                            <div class="flex items-center gap-2 rounded-2xl bg-white/10 px-3 py-2.5 ring-1 ring-white/10">
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-white/10 text-brand-200">
                                    @include('partials.icon', ['name' => 'phone', 'class' => 'size-4'])
                                </span>
                                <div class="min-w-0">
                                    <p class="text-[8.5px] font-semibold uppercase tracking-[0.14em] text-brand-200">Telepon</p>
                                    <p class="truncate text-[11px] font-semibold text-white">{{ $profile->phone ?: 'Belum diisi' }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 rounded-2xl bg-white/10 px-3 py-2.5 ring-1 ring-white/10">
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-white/10 text-brand-200">
                                    @include('partials.icon', ['name' => 'calendar', 'class' => 'size-4'])
                                </span>
                                <div class="min-w-0">
                                    <p class="text-[8.5px] font-semibold uppercase tracking-[0.14em] text-brand-200">Bergabung</p>
                                    <p class="truncate text-[11px] font-semibold text-white">{{ $formatDate($profile->created_at) }}</p>
                                </div>
                            </div>
                        </div>

                        <button
                            type="button"
                            data-open-modal="edit-profile"
                            class="mt-3 inline-flex h-10 w-full items-center justify-center gap-1.5 rounded-xl bg-surface px-4 text-[11.5px] font-semibold text-brand-200 transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/25 sm:hidden"
                        >
                            @include('partials.icon', ['name' => 'pencil', 'class' => 'size-3.5'])
                            Edit Profil
                        </button>
                    </div>
                </section>

                <section class="mt-3 grid grid-cols-3 gap-2.5 sm:gap-3" aria-label="Ringkasan aktivitas">
                    <article class="rounded-[22px] bg-surface p-3.5 shadow-[0_16px_40px_-34px_rgba(0,0,0,0.6)] ring-1 ring-white/10 sm:p-4">
                        <span class="flex size-9 items-center justify-center rounded-xl bg-brand-50 text-brand-200">
                            @include('partials.icon', ['name' => 'clipboard', 'class' => 'size-[18px]'])
                        </span>
                        <p class="mt-3 text-[24px] font-semibold leading-none tracking-[-0.04em] text-ink">{{ $stats['submitted'] }}</p>
                        <p class="mt-1.5 text-[9.5px] font-semibold leading-tight text-muted sm:text-[10.5px]">Kebutuhan Diajukan</p>
                    </article>

                    <article class="rounded-[22px] bg-surface p-3.5 shadow-[0_16px_40px_-34px_rgba(0,0,0,0.6)] ring-1 ring-white/10 sm:p-4">
                        <span class="flex size-9 items-center justify-center rounded-xl bg-accent-50 text-accent-600">
                            @include('partials.icon', ['name' => 'heart', 'class' => 'size-[18px]'])
                        </span>
                        <p class="mt-3 text-[24px] font-semibold leading-none tracking-[-0.04em] text-ink">{{ $stats['supported'] }}</p>
                        <p class="mt-1.5 text-[9.5px] font-semibold leading-tight text-muted sm:text-[10.5px]">Kebutuhan Didukung</p>
                    </article>

                    <article class="rounded-[22px] bg-surface p-3.5 shadow-[0_16px_40px_-34px_rgba(0,0,0,0.6)] ring-1 ring-white/10 sm:p-4">
                        <span class="flex size-9 items-center justify-center rounded-xl bg-brand-50 text-brand-200">
                            @include('partials.icon', ['name' => 'flag', 'class' => 'size-[18px]'])
                        </span>
                        <p class="mt-3 text-[24px] font-semibold leading-none tracking-[-0.04em] text-ink">{{ $stats['reports'] }}</p>
                        <p class="mt-1.5 text-[9.5px] font-semibold leading-tight text-muted sm:text-[10.5px]">Laporan Saya</p>
                    </article>
                </section>

                <section class="mt-5" aria-labelledby="activity-title">
                    <div class="flex items-end justify-between gap-3">
                        <div>
                            <p class="text-[9px] font-semibold uppercase tracking-[0.16em] text-brand-300">Jejak kontribusi</p>
                            <h2 id="activity-title" class="mt-0.5 text-[17px] font-semibold tracking-[-0.03em] text-ink">Riwayat aktivitas</h2>
                        </div>
                    </div>

                    <div class="no-scrollbar -mx-1 mt-3 flex gap-1.5 overflow-x-auto px-1 pb-1" role="tablist" aria-label="Jenis aktivitas">
                        <button
                            type="button"
                            role="tab"
                            data-activity-tab="submitted"
                            aria-selected="true"
                            aria-controls="activity-submitted"
                            class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full bg-brand-700 px-3.5 text-[10.5px] font-semibold text-canvas shadow-[0_10px_20px_-16px_rgba(0,0,0,0.9)] transition"
                        >
                            Diajukan
                            <span class="rounded-full bg-white/15 px-1.5 py-0.5 text-[9px]">{{ $stats['submitted'] }}</span>
                        </button>

                        <button
                            type="button"
                            role="tab"
                            data-activity-tab="supported"
                            aria-selected="false"
                            aria-controls="activity-supported"
                            class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full bg-surface px-3.5 text-[10.5px] font-semibold text-muted ring-1 ring-white/10 transition hover:text-brand-200"
                        >
                            Didukung
                            <span class="rounded-full bg-canvas px-1.5 py-0.5 text-[9px]">{{ $stats['supported'] }}</span>
                        </button>

                        <button
                            type="button"
                            role="tab"
                            data-activity-tab="reports"
                            aria-selected="false"
                            aria-controls="activity-reports"
                            class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full bg-surface px-3.5 text-[10.5px] font-semibold text-muted ring-1 ring-white/10 transition hover:text-brand-200"
                        >
                            Laporan
                            <span class="rounded-full bg-canvas px-1.5 py-0.5 text-[9px]">{{ $stats['reports'] }}</span>
                        </button>
                    </div>

                    <div class="mt-2.5">
                        <div id="activity-submitted" role="tabpanel" data-activity-panel="submitted" class="space-y-2">
                            @forelse ($submittedNeeds as $need)
                                @php
                                    $category = $categories[$need->category] ?? ($categories['all'] ?? null);
                                    $statusClass = $needStatusStyles[$need->status] ?? $needStatusStyles['aktif'];
                                @endphp

                                <article class="flex items-start gap-3 rounded-2xl bg-surface p-3.5 shadow-[0_14px_34px_-30px_rgba(0,0,0,0.6)] ring-1 ring-white/10">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl" style="background: {{ $category['soft_color'] }}; color: {{ $category['color'] }};">
                                        @include('partials.icon', ['name' => $category['icon'], 'class' => 'size-[18px]'])
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start gap-2">
                                            <h3 class="min-w-0 flex-1 truncate text-[12.5px] font-semibold text-ink">{{ $need->title }}</h3>
                                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[8.5px] font-semibold uppercase tracking-[0.08em] ring-1 {{ $statusClass }}">{{ $need->status }}</span>
                                        </div>

                                        <p class="mt-0.5 truncate text-[10px] font-medium text-muted">{{ $category['label'] }}@if ($need->location) &middot; {{ $need->location }}@endif</p>

                                        <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[9.5px] font-medium text-muted">
                                            <span class="inline-flex items-center gap-1">
                                                @include('partials.icon', ['name' => 'calendar', 'class' => 'size-3'])
                                                {{ $formatDate($need->created_at) }}
                                            </span>
                                            <span class="inline-flex items-center gap-1">
                                                @include('partials.icon', ['name' => 'heart', 'class' => 'size-3'])
                                                {{ $need->supports_count }} dukungan
                                            </span>
                                        </div>
                                    </div>
                                </article>
                            @empty
                                <div class="flex flex-col items-center rounded-[22px] bg-surface px-5 py-8 text-center ring-1 ring-white/10">
                                    <span class="flex size-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-200">
                                        @include('partials.icon', ['name' => 'clipboard', 'class' => 'size-6'])
                                    </span>
                                    <h3 class="mt-3 text-[13px] font-semibold text-ink">Belum ada kebutuhan diajukan</h3>
                                    <p class="mt-1 max-w-[260px] text-[10.5px] leading-relaxed text-muted">Kebutuhan yang kamu ajukan lewat peta akan muncul di sini.</p>
                                </div>
                            @endforelse
                        </div>

                        <div id="activity-supported" role="tabpanel" data-activity-panel="supported" class="hidden space-y-2">
                            @forelse ($supportedNeeds as $need)
                                @php
                                    $category = $categories[$need->category] ?? ($categories['all'] ?? null);
                                    $statusClass = $needStatusStyles[$need->status] ?? $needStatusStyles['aktif'];
                                @endphp

                                <article class="flex items-start gap-3 rounded-2xl bg-surface p-3.5 shadow-[0_14px_34px_-30px_rgba(0,0,0,0.6)] ring-1 ring-white/10">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl" style="background: {{ $category['soft_color'] }}; color: {{ $category['color'] }};">
                                        @include('partials.icon', ['name' => $category['icon'], 'class' => 'size-[18px]'])
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start gap-2">
                                            <h3 class="min-w-0 flex-1 truncate text-[12.5px] font-semibold text-ink">{{ $need->title }}</h3>
                                            <span class="shrink-0 rounded-full bg-accent-50 px-2 py-0.5 text-[8.5px] font-semibold uppercase tracking-[0.08em] text-accent-600 ring-1 ring-accent-500/15">Didukung</span>
                                        </div>

                                        <p class="mt-0.5 truncate text-[10px] font-medium text-muted">{{ $category['label'] }}@if ($need->location) &middot; {{ $need->location }}@endif</p>

                                        <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[9.5px] font-medium text-muted">
                                            <span class="inline-flex items-center gap-1">
                                                @include('partials.icon', ['name' => 'calendar', 'class' => 'size-3'])
                                                {{ $formatDate($need->pivot?->created_at) }}
                                            </span>
                                            <span class="inline-flex items-center gap-1">
                                                @include('partials.icon', ['name' => 'heart', 'class' => 'size-3'])
                                                {{ $need->supports_count }} dukungan
                                            </span>
                                        </div>
                                    </div>
                                </article>
                            @empty
                                <div class="flex flex-col items-center rounded-[22px] bg-surface px-5 py-8 text-center ring-1 ring-white/10">
                                    <span class="flex size-12 items-center justify-center rounded-2xl bg-accent-50 text-accent-600">
                                        @include('partials.icon', ['name' => 'heart', 'class' => 'size-6'])
                                    </span>
                                    <h3 class="mt-3 text-[13px] font-semibold text-ink">Belum ada kebutuhan didukung</h3>
                                    <p class="mt-1 max-w-[260px] text-[10.5px] leading-relaxed text-muted">Dukungan yang kamu berikan pada kebutuhan warga akan tercatat di sini.</p>
                                </div>
                            @endforelse
                        </div>

                        <div id="activity-reports" role="tabpanel" data-activity-panel="reports" class="hidden space-y-2">
                            @forelse ($reports as $report)
                                @php
                                    $reportCategory = $report['category'] ?? $report['need_category'];
                                    $category = $categories[$reportCategory] ?? ($categories['all'] ?? null);
                                    $statusClass = $reportStatusStyles[$report['status']] ?? $reportStatusStyles['diproses'];
                                    $reportTitle = $report['title'] ?? $report['need_title'] ?? 'Laporan kebutuhan';
                                @endphp

                                <article class="flex items-start gap-3 rounded-2xl bg-surface p-3.5 shadow-[0_14px_34px_-30px_rgba(0,0,0,0.6)] ring-1 ring-white/10">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl" style="background: {{ $category['soft_color'] }}; color: {{ $category['color'] }};">
                                        @include('partials.icon', ['name' => 'flag', 'class' => 'size-[18px]'])
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start gap-2">
                                            <h3 class="min-w-0 flex-1 truncate text-[12.5px] font-semibold text-ink">{{ $reportTitle }}</h3>
                                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[8.5px] font-semibold uppercase tracking-[0.08em] ring-1 {{ $statusClass }}">{{ $report['status'] }}</span>
                                        </div>

                                        <p class="mt-0.5 truncate text-[10px] font-medium text-muted">{{ $category['label'] }}@if ($report['reason']) &middot; {{ $report['reason'] }}@endif</p>

                                        <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[9.5px] font-medium text-muted">
                                            <span class="inline-flex items-center gap-1">
                                                @include('partials.icon', ['name' => 'calendar', 'class' => 'size-3'])
                                                {{ $formatDate($report['created_at']) }}
                                            </span>
                                            <span class="inline-flex items-center gap-1">
                                                @include('partials.icon', ['name' => 'clipboard', 'class' => 'size-3'])
                                                Laporan ketidaksesuaian
                                            </span>
                                        </div>
                                    </div>
                                </article>
                            @empty
                                <div class="flex flex-col items-center rounded-[22px] bg-surface px-5 py-8 text-center ring-1 ring-white/10">
                                    <span class="flex size-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-200">
                                        @include('partials.icon', ['name' => 'flag', 'class' => 'size-6'])
                                    </span>
                                    <h3 class="mt-3 text-[13px] font-semibold text-ink">Belum ada laporan</h3>
                                    <p class="mt-1 max-w-[260px] text-[10.5px] leading-relaxed text-muted">Laporan ketidaksesuaian yang kamu kirim akan tampil di sini.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </section>

                <section class="mt-5" aria-labelledby="settings-title">
                    <div class="flex items-center gap-2">
                        <span class="flex size-8 items-center justify-center rounded-xl bg-brand-50 text-brand-200">
                            @include('partials.icon', ['name' => 'settings', 'class' => 'size-4'])
                        </span>
                        <div>
                            <p class="text-[9px] font-semibold uppercase tracking-[0.16em] text-brand-300">Kelola akun</p>
                            <h2 id="settings-title" class="text-[17px] font-semibold tracking-[-0.03em] text-ink">Pengaturan akun</h2>
                        </div>
                    </div>

                    <div class="mt-3 overflow-hidden rounded-[24px] bg-surface shadow-[0_18px_45px_-38px_rgba(0,0,0,0.65)] ring-1 ring-white/10">
                        @foreach ($settingsItems as $item)
                            <button
                                type="button"
                                data-open-modal="{{ $item['modal'] }}"
                                class="group flex w-full items-center gap-3 border-b border-white/5 px-4 py-3 text-left transition last:border-b-0 hover:bg-canvas focus-visible:outline-none focus-visible:bg-canvas {{ ! empty($item['danger']) ? 'text-[#FF9B9B]' : 'text-ink' }}"
                            >
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl transition {{ ! empty($item['danger']) ? 'bg-[#3A1518] text-[#FF9B9B]' : 'bg-brand-50 text-brand-200 group-hover:bg-brand-100' }}">
                                    @include('partials.icon', ['name' => $item['icon'], 'class' => 'size-[18px]'])
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span class="block text-[12px] font-semibold">{{ $item['title'] }}</span>
                                    <span class="mt-0.5 block truncate text-[9.5px] font-medium {{ ! empty($item['danger']) ? 'text-[#FF9B9B]/70' : 'text-muted' }}">{{ $item['description'] }}</span>
                                </span>

                                @include('partials.icon', ['name' => 'chevron-right', 'class' => 'size-4 shrink-0 ' . (! empty($item['danger']) ? 'text-[#FF9B9B]/50' : 'text-muted/60')])
                            </button>
                        @endforeach
                    </div>
                </section>

                <p class="mt-5 text-center text-[9px] font-medium uppercase tracking-[0.16em] text-muted/70">nearFind &middot; Peta kebutuhan warga</p>
            </div>
        </main>
    </div>

    <div id="profile-modal-edit-profile" data-profile-modal="edit-profile" class="fixed inset-0 z-[1300] hidden items-end justify-center lg:items-center" role="presentation">
        <div data-modal-backdrop class="absolute inset-0 bg-black/65 backdrop-blur-[2px]"></div>

        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="edit-profile-title"
            class="profile-modal-panel relative z-10 max-h-[94dvh] w-full max-w-[560px] overflow-y-auto rounded-t-[28px] bg-surface px-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-3 shadow-[0_-24px_70px_-30px_rgba(0,0,0,0.55)] lg:max-w-[500px] lg:rounded-[28px] lg:p-6"
        >
            <div class="mx-auto mb-4 h-1 w-10 rounded-full bg-white/10 lg:hidden"></div>

            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[9px] font-semibold uppercase tracking-[0.16em] text-brand-300">Informasi pribadi</p>
                    <h2 id="edit-profile-title" class="mt-1 text-lg font-semibold tracking-[-0.02em] text-ink">Edit profil</h2>
                </div>

                <button type="button" data-modal-close class="flex size-9 shrink-0 items-center justify-center rounded-full bg-canvas text-muted transition hover:bg-brand-50 hover:text-brand-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15" aria-label="Tutup edit profil">
                    @include('partials.icon', ['name' => 'close', 'class' => 'size-4'])
                </button>
            </div>

            <form method="POST" action="{{ route('profile.update') }}" class="mt-5 space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="_form" value="edit-profile">

                {{-- No avatar upload: the default avatar is derived from the row id. --}}
                <div class="flex items-center gap-3 rounded-2xl bg-canvas p-3.5 ring-1 ring-white/5">
                    <div class="nf-avatar nf-avatar--{{ $profile->avatar_variant }} flex size-12 shrink-0 items-center justify-center rounded-2xl text-sm font-semibold text-white ring-2 ring-white">
                        {{ $profile->initials }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[10.5px] font-semibold text-brand-200">Avatar bawaan</p>
                        <p class="mt-0.5 text-[9px] leading-relaxed text-muted">Foto profil kustom belum tersedia. Avatar dipilih otomatis dari akunmu.</p>
                    </div>
                </div>

                <div>
                    <label for="name" class="text-[11px] font-semibold text-brand-200">Nama lengkap</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $profile->name) }}" required maxlength="100" autocomplete="name" class="mt-1.5 h-11 w-full rounded-xl border-0 bg-canvas px-3.5 text-[12.5px] text-ink ring-1 ring-white/10 transition placeholder:text-muted/70 focus:bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand-500/40">
                    @error('name')
                        <p class="mt-1 text-[10px] font-medium text-[#FF9B9B]">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="username" class="text-[11px] font-semibold text-brand-200">Username</label>
                        <div class="relative mt-1.5">
                            <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-[12.5px] font-medium text-muted">@</span>
                            <input id="username" name="username" type="text" value="{{ old('username', $profile->username) }}" maxlength="30" autocomplete="username" placeholder="nama.pengguna" class="h-11 w-full rounded-xl border-0 bg-canvas pl-8 pr-3 text-[12.5px] text-ink ring-1 ring-white/10 transition placeholder:text-muted/70 focus:bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand-500/40">
                        </div>
                        @error('username')
                            <p class="mt-1 text-[10px] font-medium text-[#FF9B9B]">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="phone" class="text-[11px] font-semibold text-brand-200">Nomor telepon</label>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone', $profile->phone) }}" maxlength="25" autocomplete="tel" placeholder="08xx xxxx xxxx" class="mt-1.5 h-11 w-full rounded-xl border-0 bg-canvas px-3.5 text-[12.5px] text-ink ring-1 ring-white/10 transition placeholder:text-muted/70 focus:bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand-500/40">
                        @error('phone')
                            <p class="mt-1 text-[10px] font-medium text-[#FF9B9B]">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between gap-2">
                        <label for="bio" class="text-[11px] font-semibold text-brand-200">Bio</label>
                        <span class="text-[9.5px] font-medium text-muted"><span data-bio-count>{{ mb_strlen(old('bio', $profile->bio ?? '')) }}</span>/300</span>
                    </div>
                    <textarea id="bio" name="bio" rows="3" maxlength="300" data-bio-input class="mt-1.5 w-full rounded-xl border-0 bg-canvas px-3.5 py-3 text-[12.5px] leading-relaxed text-ink ring-1 ring-white/10 transition placeholder:text-muted/70 focus:bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand-500/40" placeholder="Ceritakan peranmu di lingkungan sekitar...">{{ old('bio', $profile->bio) }}</textarea>
                    @error('bio')
                        <p class="mt-1 text-[10px] font-medium text-[#FF9B9B]">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex gap-2.5 pt-1">
                    <button type="button" data-modal-close class="inline-flex h-11 flex-1 items-center justify-center rounded-xl bg-canvas px-4 text-[12px] font-semibold text-brand-200 ring-1 ring-white/10 transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15">
                        Batal
                    </button>

                    <button type="submit" class="inline-flex h-11 flex-[1.6] items-center justify-center gap-2 rounded-xl bg-brand-700 px-4 text-[12px] font-semibold text-canvas shadow-[0_12px_24px_-16px_rgba(0,0,0,0.8)] transition hover:bg-brand-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20">
                        @include('partials.icon', ['name' => 'check', 'class' => 'size-4'])
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="profile-modal-password" data-profile-modal="password" class="fixed inset-0 z-[1300] hidden items-end justify-center lg:items-center" role="presentation">
        <div data-modal-backdrop class="absolute inset-0 bg-black/65 backdrop-blur-[2px]"></div>

        <div role="dialog" aria-modal="true" aria-labelledby="password-title" class="profile-modal-panel relative z-10 max-h-[94dvh] w-full max-w-[560px] overflow-y-auto rounded-t-[28px] bg-surface px-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-3 shadow-[0_-24px_70px_-30px_rgba(0,0,0,0.55)] lg:max-w-[460px] lg:rounded-[28px] lg:p-6">
            <div class="mx-auto mb-4 h-1 w-10 rounded-full bg-white/10 lg:hidden"></div>

            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[9px] font-semibold uppercase tracking-[0.16em] text-brand-300">Keamanan akun</p>
                    <h2 id="password-title" class="mt-1 text-lg font-semibold tracking-[-0.02em] text-ink">Ubah password</h2>
                </div>

                <button type="button" data-modal-close class="flex size-9 shrink-0 items-center justify-center rounded-full bg-canvas text-muted transition hover:bg-brand-50 hover:text-brand-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15" aria-label="Tutup ubah password">
                    @include('partials.icon', ['name' => 'close', 'class' => 'size-4'])
                </button>
            </div>

            <form method="POST" action="{{ route('profile.password') }}" class="mt-5 space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="_form" value="password">

                <div>
                    <label for="current_password" class="text-[11px] font-semibold text-brand-200">Kata sandi saat ini</label>
                    <div class="relative mt-1.5">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-muted">
                            @include('partials.icon', ['name' => 'lock', 'class' => 'size-4'])
                        </span>
                        <input id="current_password" name="current_password" type="password" required autocomplete="current-password" data-password-input class="h-11 w-full rounded-xl border-0 bg-canvas pl-10 pr-11 text-[12.5px] text-ink ring-1 ring-white/10 transition focus:bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand-500/40">
                        <button type="button" data-password-toggle class="absolute inset-y-0 right-1.5 my-auto flex size-8 items-center justify-center rounded-lg text-muted transition hover:bg-brand-50 hover:text-brand-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15" aria-label="Tampilkan kata sandi" aria-pressed="false">
                            @include('partials.icon', ['name' => 'eye', 'class' => 'size-4'])
                        </button>
                    </div>
                    @error('current_password')
                        <p class="mt-1 text-[10px] font-medium text-[#FF9B9B]">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="text-[11px] font-semibold text-brand-200">Kata sandi baru</label>
                    <div class="relative mt-1.5">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-muted">
                            @include('partials.icon', ['name' => 'lock', 'class' => 'size-4'])
                        </span>
                        <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" data-password-input class="h-11 w-full rounded-xl border-0 bg-canvas pl-10 pr-11 text-[12.5px] text-ink ring-1 ring-white/10 transition focus:bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand-500/40">
                        <button type="button" data-password-toggle class="absolute inset-y-0 right-1.5 my-auto flex size-8 items-center justify-center rounded-lg text-muted transition hover:bg-brand-50 hover:text-brand-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15" aria-label="Tampilkan kata sandi" aria-pressed="false">
                            @include('partials.icon', ['name' => 'eye', 'class' => 'size-4'])
                        </button>
                    </div>
                    @error('password')
                        <p class="mt-1 text-[10px] font-medium text-[#FF9B9B]">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="text-[11px] font-semibold text-brand-200">Ulangi kata sandi baru</label>
                    <div class="relative mt-1.5">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-muted">
                            @include('partials.icon', ['name' => 'lock', 'class' => 'size-4'])
                        </span>
                        <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password" data-password-input class="h-11 w-full rounded-xl border-0 bg-canvas pl-10 pr-11 text-[12.5px] text-ink ring-1 ring-white/10 transition focus:bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand-500/40">
                        <button type="button" data-password-toggle class="absolute inset-y-0 right-1.5 my-auto flex size-8 items-center justify-center rounded-lg text-muted transition hover:bg-brand-50 hover:text-brand-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15" aria-label="Tampilkan kata sandi" aria-pressed="false">
                            @include('partials.icon', ['name' => 'eye', 'class' => 'size-4'])
                        </button>
                    </div>
                </div>

                <div class="flex items-start gap-2.5 rounded-2xl bg-canvas px-3.5 py-3 ring-1 ring-white/5">
                    @include('partials.icon', ['name' => 'info', 'class' => 'mt-0.5 size-4 shrink-0 text-brand-300'])
                    <p class="text-[10px] leading-relaxed text-muted">Gunakan minimal 8 karakter dan hindari kata sandi yang sama dengan akun lain.</p>
                </div>

                <div class="flex gap-2.5 pt-1">
                    <button type="button" data-modal-close class="inline-flex h-11 flex-1 items-center justify-center rounded-xl bg-canvas px-4 text-[12px] font-semibold text-brand-200 ring-1 ring-white/10 transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15">
                        Batal
                    </button>

                    <button type="submit" class="inline-flex h-11 flex-[1.6] items-center justify-center gap-2 rounded-xl bg-brand-700 px-4 text-[12px] font-semibold text-canvas shadow-[0_12px_24px_-16px_rgba(0,0,0,0.8)] transition hover:bg-brand-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20">
                        @include('partials.icon', ['name' => 'lock', 'class' => 'size-4'])
                        Simpan Kata Sandi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="profile-modal-notifications" data-profile-modal="notifications" class="fixed inset-0 z-[1300] hidden items-end justify-center lg:items-center" role="presentation">
        <div data-modal-backdrop class="absolute inset-0 bg-black/65 backdrop-blur-[2px]"></div>

        <div role="dialog" aria-modal="true" aria-labelledby="notifications-title" class="profile-modal-panel relative z-10 max-h-[94dvh] w-full max-w-[560px] overflow-y-auto rounded-t-[28px] bg-surface px-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-3 shadow-[0_-24px_70px_-30px_rgba(0,0,0,0.55)] lg:max-w-[460px] lg:rounded-[28px] lg:p-6">
            <div class="mx-auto mb-4 h-1 w-10 rounded-full bg-white/10 lg:hidden"></div>

            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[9px] font-semibold uppercase tracking-[0.16em] text-brand-300">Preferensi</p>
                    <h2 id="notifications-title" class="mt-1 text-lg font-semibold tracking-[-0.02em] text-ink">Pengaturan notifikasi</h2>
                </div>

                <button type="button" data-modal-close class="flex size-9 shrink-0 items-center justify-center rounded-full bg-canvas text-muted transition hover:bg-brand-50 hover:text-brand-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15" aria-label="Tutup pengaturan notifikasi">
                    @include('partials.icon', ['name' => 'close', 'class' => 'size-4'])
                </button>
            </div>

            <form method="POST" action="{{ route('profile.notifications') }}" class="mt-5 space-y-3">
                @csrf
                @method('PUT')

                <label class="flex cursor-pointer items-start gap-3 rounded-2xl bg-canvas p-3.5 ring-1 ring-white/5 transition hover:bg-brand-50/60">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-200">
                        @include('partials.icon', ['name' => 'bell', 'class' => 'size-[18px]'])
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[11.5px] font-semibold text-ink">Kabar kebutuhan sekitar</span>
                        <span class="mt-0.5 block text-[9.5px] leading-relaxed text-muted">Beri tahu saat ada kebutuhan baru di area sekitarmu.</span>
                    </span>
                    <input type="checkbox" name="need_updates" value="1" @checked($notificationSettings['need_updates']) class="mt-1 size-4 shrink-0 rounded border-white/20 accent-brand-700 focus:ring-brand-500/30">
                </label>

                <label class="flex cursor-pointer items-start gap-3 rounded-2xl bg-canvas p-3.5 ring-1 ring-white/5 transition hover:bg-brand-50/60">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-accent-50 text-accent-600">
                        @include('partials.icon', ['name' => 'heart', 'class' => 'size-[18px]'])
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[11.5px] font-semibold text-ink">Aktivitas dukungan</span>
                        <span class="mt-0.5 block text-[9.5px] leading-relaxed text-muted">Kabar saat kebutuhan yang kamu dukung mendapat pembaruan.</span>
                    </span>
                    <input type="checkbox" name="support_activity" value="1" @checked($notificationSettings['support_activity']) class="mt-1 size-4 shrink-0 rounded border-white/20 accent-brand-700 focus:ring-brand-500/30">
                </label>

                <label class="flex cursor-pointer items-start gap-3 rounded-2xl bg-canvas p-3.5 ring-1 ring-white/5 transition hover:bg-brand-50/60">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-200">
                        @include('partials.icon', ['name' => 'clipboard', 'class' => 'size-[18px]'])
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[11.5px] font-semibold text-ink">Rangkuman mingguan</span>
                        <span class="mt-0.5 block text-[9.5px] leading-relaxed text-muted">Terima ringkasan kebutuhan dan laporan setiap pekan.</span>
                    </span>
                    <input type="checkbox" name="weekly_digest" value="1" @checked($notificationSettings['weekly_digest']) class="mt-1 size-4 shrink-0 rounded border-white/20 accent-brand-700 focus:ring-brand-500/30">
                </label>

                <div class="flex gap-2.5 pt-1">
                    <button type="button" data-modal-close class="inline-flex h-11 flex-1 items-center justify-center rounded-xl bg-canvas px-4 text-[12px] font-semibold text-brand-200 ring-1 ring-white/10 transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15">
                        Batal
                    </button>

                    <button type="submit" class="inline-flex h-11 flex-[1.6] items-center justify-center gap-2 rounded-xl bg-brand-700 px-4 text-[12px] font-semibold text-canvas shadow-[0_12px_24px_-16px_rgba(0,0,0,0.8)] transition hover:bg-brand-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20">
                        @include('partials.icon', ['name' => 'check', 'class' => 'size-4'])
                        Simpan Pengaturan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="profile-modal-help" data-profile-modal="help" class="fixed inset-0 z-[1300] hidden items-end justify-center lg:items-center" role="presentation">
        <div data-modal-backdrop class="absolute inset-0 bg-black/65 backdrop-blur-[2px]"></div>

        <div role="dialog" aria-modal="true" aria-labelledby="help-title" class="profile-modal-panel relative z-10 max-h-[94dvh] w-full max-w-[560px] overflow-y-auto rounded-t-[28px] bg-surface px-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-3 shadow-[0_-24px_70px_-30px_rgba(0,0,0,0.55)] lg:max-w-[460px] lg:rounded-[28px] lg:p-6">
            <div class="mx-auto mb-4 h-1 w-10 rounded-full bg-white/10 lg:hidden"></div>

            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[9px] font-semibold uppercase tracking-[0.16em] text-brand-300">Panduan</p>
                    <h2 id="help-title" class="mt-1 text-lg font-semibold tracking-[-0.02em] text-ink">Bantuan</h2>
                </div>

                <button type="button" data-modal-close class="flex size-9 shrink-0 items-center justify-center rounded-full bg-canvas text-muted transition hover:bg-brand-50 hover:text-brand-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15" aria-label="Tutup bantuan">
                    @include('partials.icon', ['name' => 'close', 'class' => 'size-4'])
                </button>
            </div>

            <div class="mt-5 space-y-2.5">
                <div class="flex items-start gap-3 rounded-2xl bg-canvas p-3.5 ring-1 ring-white/5">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-200">
                        @include('partials.icon', ['name' => 'map', 'class' => 'size-[18px]'])
                    </span>
                    <div>
                        <p class="text-[11.5px] font-semibold text-ink">Menjelajah kebutuhan</p>
                        <p class="mt-0.5 text-[10px] leading-relaxed text-muted">Gunakan peta untuk melihat kebutuhan warga di sekitar Sukajadi dan sekitarnya.</p>
                    </div>
                </div>

                <div class="flex items-start gap-3 rounded-2xl bg-canvas p-3.5 ring-1 ring-white/5">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-accent-50 text-accent-600">
                        @include('partials.icon', ['name' => 'heart', 'class' => 'size-[18px]'])
                    </span>
                    <div>
                        <p class="text-[11.5px] font-semibold text-ink">Mendukung kebutuhan</p>
                        <p class="mt-0.5 text-[10px] leading-relaxed text-muted">Tekan tombol dukungan pada kartu kebutuhan agar prioritasnya terlihat.</p>
                    </div>
                </div>

                <div class="flex items-start gap-3 rounded-2xl bg-canvas p-3.5 ring-1 ring-white/5">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-200">
                        @include('partials.icon', ['name' => 'flag', 'class' => 'size-[18px]'])
                    </span>
                    <div>
                        <p class="text-[11.5px] font-semibold text-ink">Melaporkan ketidaksesuaian</p>
                        <p class="mt-0.5 text-[10px] leading-relaxed text-muted">Laporkan informasi yang tidak sesuai supaya peta tetap tepercaya.</p>
                    </div>
                </div>
            </div>

            <div class="mt-4 rounded-2xl bg-brand-800 p-4 text-white">
                <p class="text-[9px] font-semibold uppercase tracking-[0.16em] text-brand-200">Butuh bantuan lain?</p>
                <p class="mt-1 text-[11.5px] font-semibold">Hubungi pengurus RW nearFind</p>
                <a href="mailto:halo@nearfind.id" class="mt-2 inline-flex items-center gap-1.5 rounded-lg text-[10.5px] font-semibold text-accent-100 transition hover:text-white">
                    @include('partials.icon', ['name' => 'mail', 'class' => 'size-3.5'])
                    halo@nearfind.id
                </a>
            </div>

            <button type="button" data-modal-close class="mt-4 inline-flex h-11 w-full items-center justify-center rounded-xl bg-canvas px-4 text-[12px] font-semibold text-brand-200 ring-1 ring-white/10 transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15">
                Tutup
            </button>
        </div>
    </div>

    <div id="profile-modal-logout" data-profile-modal="logout" class="fixed inset-0 z-[1300] hidden items-end justify-center lg:items-center" role="presentation">
        <div data-modal-backdrop class="absolute inset-0 bg-black/65 backdrop-blur-[2px]"></div>

        <div role="dialog" aria-modal="true" aria-labelledby="logout-title" class="profile-modal-panel relative z-10 w-full max-w-[560px] rounded-t-[28px] bg-surface px-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-3 shadow-[0_-24px_70px_-30px_rgba(0,0,0,0.55)] lg:max-w-[420px] lg:rounded-[28px] lg:p-6">
            <div class="mx-auto mb-4 h-1 w-10 rounded-full bg-white/10 lg:hidden"></div>

            <div class="flex items-start gap-3">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-[#3A1518] text-[#FF9B9B] ring-1 ring-[#FF9B9B]/15">
                    @include('partials.icon', ['name' => 'log-out', 'class' => 'size-6'])
                </span>

                <div class="min-w-0 flex-1">
                    <p class="text-[9px] font-semibold uppercase tracking-[0.16em] text-[#FF9B9B]">Keluar akun</p>
                    <h2 id="logout-title" class="mt-1 text-lg font-semibold tracking-[-0.02em] text-ink">Yakin ingin logout?</h2>
                </div>

                <button type="button" data-modal-close class="flex size-9 shrink-0 items-center justify-center rounded-full bg-canvas text-muted transition hover:bg-brand-50 hover:text-brand-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15" aria-label="Tutup konfirmasi logout">
                    @include('partials.icon', ['name' => 'close', 'class' => 'size-4'])
                </button>
            </div>

            <p class="mt-3 text-[11.5px] leading-relaxed text-muted">Kamu perlu masuk kembali untuk mendukung kebutuhan dan melihat riwayat aktivitasmu.</p>

            <form method="POST" action="{{ route('logout') }}" class="mt-5 flex gap-2.5">
                @csrf

                <button type="button" data-modal-close class="inline-flex h-11 flex-1 items-center justify-center rounded-xl bg-canvas px-4 text-[12px] font-semibold text-brand-200 ring-1 ring-white/10 transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15">
                    Batal
                </button>

                <button type="submit" class="inline-flex h-11 flex-[1.35] items-center justify-center gap-2 rounded-xl bg-[#B91C1C] px-4 text-[12px] font-semibold text-white shadow-[0_14px_26px_-16px_rgba(159,29,29,0.8)] transition hover:bg-[#991B1B] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#FF9B9B]/25">
                    @include('partials.icon', ['name' => 'log-out', 'class' => 'size-4'])
                    Keluar
                </button>
            </form>
        </div>
    </div>
@endsection
