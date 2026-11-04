@php
    $categories = $categories ?? null;
@endphp
@php
    $sortOptions = [
        ['value' => 'relevance', 'label' => 'Paling relevan', 'description' => 'Sesuai urutan rekomendasi'],
        ['value' => 'newest', 'label' => 'Terbaru', 'description' => 'Laporan paling baru'],
        ['value' => 'supported', 'label' => 'Paling didukung', 'description' => 'Banyak warga membutuhkan'],
        ['value' => 'nearest', 'label' => 'Terdekat', 'description' => 'Dekat dengan lokasimu'],
    ];
@endphp

<div id="filter-sheet" class="fixed inset-0 z-[1200] hidden items-end justify-center lg:items-center" role="presentation">
    <div data-filter-backdrop class="absolute inset-0 bg-brand-900/35 backdrop-blur-[2px]"></div>

    <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="filter-title"
        class="filter-sheet-panel relative z-10 max-h-[92dvh] w-full max-w-[560px] overflow-y-auto rounded-t-[28px] bg-white px-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-3 shadow-[0_-24px_70px_-30px_rgba(0,60,55,0.55)] lg:max-h-[88dvh] lg:max-w-[460px] lg:rounded-[28px] lg:p-6"
    >
        <div class="mx-auto mb-4 h-1 w-10 rounded-full bg-brand-900/10 lg:hidden"></div>

        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-[9px] font-semibold uppercase tracking-[0.16em] text-brand-600">Sesuaikan peta</p>
                <h2 id="filter-title" class="mt-1 text-lg font-semibold tracking-[-0.02em] text-ink">Filter kebutuhan</h2>
            </div>

            <button
                type="button"
                data-filter-close
                class="flex size-9 shrink-0 items-center justify-center rounded-full bg-canvas text-muted transition hover:bg-brand-50 hover:text-brand-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15"
                aria-label="Tutup filter"
            >
                @include('partials.icon', ['name' => 'close', 'class' => 'size-4'])
            </button>
        </div>

        <form id="filter-form" class="mt-5">
            <fieldset>
                <legend class="text-[11px] font-semibold text-ink">Kategori</legend>

                <div class="mt-2.5 grid grid-cols-2 gap-2">
                    @foreach ($categories as $category)
                        <label class="filter-option block cursor-pointer">
                            <input
                                type="radio"
                                name="filter-category"
                                value="{{ $category['id'] }}"
                                class="sr-only"
                                @checked($category['id'] === 'all')
                            >

                            <span class="filter-option-inner flex items-center gap-2 rounded-2xl border border-brand-900/10 bg-canvas px-2.5 py-2.5 transition">
                                <span class="flex size-7 shrink-0 items-center justify-center rounded-xl" style="background: {{ $category['soft_color'] }}; color: {{ $category['color'] }};">
                                    @include('partials.icon', ['name' => $category['icon'], 'class' => 'size-3.5'])
                                </span>

                                <span class="min-w-0 flex-1 text-[10.5px] font-semibold leading-tight text-ink">{{ $category['label'] }}</span>

                                <span class="filter-check flex size-4 shrink-0 items-center justify-center rounded-full border border-brand-900/15 text-transparent transition">
                                    @include('partials.icon', ['name' => 'check', 'class' => 'size-2.5', 'strokeWidth' => '2.5'])
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <fieldset class="mt-5">
                <legend class="text-[11px] font-semibold text-ink">Urutkan</legend>

                <div class="mt-2.5 space-y-1.5">
                    @foreach ($sortOptions as $sortOption)
                        <label class="filter-option block cursor-pointer">
                            <input
                                type="radio"
                                name="filter-sort"
                                value="{{ $sortOption['value'] }}"
                                class="sr-only"
                                @checked($sortOption['value'] === 'relevance')
                            >

                            <span class="filter-sort-inner flex items-center gap-3 rounded-2xl border border-brand-900/10 bg-canvas px-3 py-2.5 transition">
                                <span class="filter-radio flex size-4 shrink-0 items-center justify-center rounded-full border border-brand-900/20 bg-white transition">
                                    <span class="size-1.5 rounded-full bg-white transition"></span>
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span class="block text-[11px] font-semibold text-ink">{{ $sortOption['label'] }}</span>
                                    <span class="block text-[9.5px] text-muted">{{ $sortOption['description'] }}</span>
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="mt-5 flex gap-2.5">
                <button
                    type="button"
                    id="filter-reset"
                    class="inline-flex h-11 flex-1 items-center justify-center rounded-xl bg-canvas px-4 text-[12px] font-semibold text-brand-700 ring-1 ring-brand-900/10 transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15"
                >
                    Atur ulang
                </button>

                <button
                    type="submit"
                    class="inline-flex h-11 flex-[1.6] items-center justify-center rounded-xl bg-brand-700 px-4 text-[12px] font-semibold text-white shadow-[0_12px_24px_-16px_rgba(0,92,85,0.8)] transition hover:bg-brand-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20"
                >
                    Terapkan filter
                </button>
            </div>
        </form>
    </div>
</div>
