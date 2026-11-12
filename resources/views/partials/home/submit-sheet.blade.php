<div
    id="submit-sheet"
    data-initial-open="{{ $errors->any() ? 'true' : 'false' }}"
    class="fixed inset-0 z-[1250] hidden items-end justify-center lg:items-center"
    role="presentation"
>
    <div data-submit-backdrop class="absolute inset-0 bg-black/60 backdrop-blur-[2px]"></div>

    <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="submit-sheet-title"
        aria-describedby="submit-sheet-description"
        class="submit-sheet-panel relative z-10 max-h-[92dvh] w-full max-w-[560px] overflow-y-auto rounded-t-[28px] bg-surface px-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-3 shadow-[0_-24px_70px_-30px_rgba(0,0,0,0.55)] lg:max-h-[88dvh] lg:max-w-[460px] lg:rounded-[28px] lg:p-6"
    >
        <div class="mx-auto mb-4 h-1 w-10 rounded-full bg-white/10 lg:hidden"></div>

        <div class="flex items-start gap-3">
            <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-200 ring-1 ring-white/10">
                @include('partials.icon', ['name' => 'plus', 'class' => 'size-6', 'strokeWidth' => '2.2'])
            </span>

            <div class="min-w-0 flex-1">
                <p class="text-[9px] font-semibold uppercase tracking-[0.16em] text-brand-300">Kebutuhan baru</p>
                <h2 id="submit-sheet-title" class="mt-1 text-xl font-semibold tracking-[-0.025em] text-ink">Ajukan kebutuhan</h2>
            </div>

            <button
                type="button"
                data-submit-close
                class="flex size-9 shrink-0 items-center justify-center rounded-full bg-canvas text-muted transition hover:bg-brand-50 hover:text-brand-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15"
                aria-label="Tutup form kebutuhan"
            >
                @include('partials.icon', ['name' => 'close', 'class' => 'size-4'])
            </button>
        </div>

        <p id="submit-sheet-description" class="mt-3 text-[12px] leading-relaxed text-muted">
            Tulis kebutuhan yang belum ada di peta. Warga lain bisa melihat dan mendukungnya lewat profilmu.
        </p>

        <form method="POST" action="{{ route('needs.store') }}" class="mt-5 space-y-4">
            @csrf

            <div>
                <label for="need-title" class="text-[11px] font-semibold text-brand-200">Judul kebutuhan</label>

                <div class="relative mt-1.5">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-muted">
                        @include('partials.icon', ['name' => 'sparkle', 'class' => 'size-4'])
                    </span>

                    <input
                        id="need-title"
                        name="title"
                        type="text"
                        value="{{ old('title') }}"
                        required
                        minlength="3"
                        maxlength="120"
                        autocomplete="off"
                        placeholder="Contoh: Jasa servis kipas angin"
                        class="h-11 w-full rounded-xl border-0 bg-canvas pl-10 pr-3 text-[12.5px] text-ink ring-1 ring-white/10 transition placeholder:text-muted/70 focus:bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand-500/40"
                    >
                </div>

                @error('title')
                    <p class="mt-1 text-[10px] font-medium text-[#FF9B9B]">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="need-category" class="text-[11px] font-semibold text-brand-200">Kategori</label>

                <div class="relative mt-1.5">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-muted">
                        @include('partials.icon', ['name' => 'grid', 'class' => 'size-4'])
                    </span>

                    <select
                        id="need-category"
                        name="category"
                        required
                        class="h-11 w-full appearance-none rounded-xl border-0 bg-canvas pl-10 pr-10 text-[12.5px] text-ink ring-1 ring-white/10 transition focus:bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand-500/40"
                    >
                        <option value="" disabled {{ old('category') ? '' : 'selected' }}>Pilih kategori</option>

                        @foreach ($categories as $category)
                            @if ($category['id'] !== 'all')
                                <option value="{{ $category['id'] }}" {{ old('category') === $category['id'] ? 'selected' : '' }}>
                                    {{ $category['label'] }}
                                </option>
                            @endif
                        @endforeach
                    </select>

                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-muted">
                        @include('partials.icon', ['name' => 'chevron-down', 'class' => 'size-4'])
                    </span>
                </div>

                @error('category')
                    <p class="mt-1 text-[10px] font-medium text-[#FF9B9B]">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <div class="flex items-baseline justify-between gap-3">
                    <label id="need-point-label" class="text-[11px] font-semibold text-brand-200">Titik lokasi <span class="font-normal text-muted">(wajib)</span></label>

                    <button
                        type="button"
                        data-point-locate
                        class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-2.5 py-1 text-[10px] font-semibold text-brand-200 ring-1 ring-white/10 transition hover:bg-brand-100 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15"
                    >
                        @include('partials.icon', ['name' => 'pin', 'class' => 'size-3'])
                        Pakai lokasiku
                    </button>
                </div>

                {{-- Tapping this map is how a visitor says WHERE the need is. The pin
                     stays centred while the map pans underneath it, which works the
                     same with a mouse and with a thumb. --}}
                <div
                    data-point-picker
                    class="relative mt-1.5 h-[176px] overflow-hidden rounded-xl bg-[#1a1d21] ring-1 ring-white/10 transition focus-within:ring-2 focus-within:ring-brand-500/40"
                >
                    <div data-point-canvas class="absolute inset-0 h-full w-full"></div>

                    {{-- Bottom, not top: the pin hangs from the centre upwards and a
                         top banner sat right on top of it. --}}
                    <div data-point-hint class="pointer-events-none absolute inset-x-2 bottom-2 z-10 rounded-lg bg-black/60 px-2.5 py-1.5 text-center text-[10px] font-medium text-white backdrop-blur-sm">
                        Geser peta untuk menaruh pin
                    </div>

                    {{-- The pin tip, not the icon box, must sit on the map centre. --}}
                    <span class="pointer-events-none absolute left-1/2 top-1/2 z-[5] -translate-x-1/2 -translate-y-full">
                        @include('partials.icon', ['name' => 'pin', 'class' => 'block size-8 drop-shadow-[0_4px_8px_rgba(0,0,0,0.6)] text-brand-200'])
                    </span>

                    <span class="pointer-events-none absolute left-1/2 top-1/2 z-[4] size-2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand-700 ring-2 ring-canvas"></span>

                    <div data-point-busy class="absolute inset-x-2 top-2 z-20 hidden justify-center">
                        <span class="rounded-lg bg-black/70 px-2.5 py-1.5 text-[10px] font-semibold text-white backdrop-blur-sm">Mencari alamat…</span>
                    </div>
                </div>

                <input type="hidden" name="latitude" value="{{ old('latitude') }}" data-point-lat>
                <input type="hidden" name="longitude" value="{{ old('longitude') }}" data-point-lng>

                <p data-point-summary class="mt-2 text-[11px] font-medium leading-relaxed text-brand-200">
                    Geser peta sampai pin berada di lokasi kebutuhan.
                </p>

                <div class="mt-2 flex flex-wrap gap-1.5">
                    <button type="button" data-point-step="west" class="size-8 rounded-lg bg-canvas text-[13px] text-brand-200 ring-1 ring-white/10 transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15" aria-label="Geser peta ke barat">←</button>
                    <button type="button" data-point-step="north" class="size-8 rounded-lg bg-canvas text-[13px] text-brand-200 ring-1 ring-white/10 transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15" aria-label="Geser peta ke utara">↑</button>
                    <button type="button" data-point-step="south" class="size-8 rounded-lg bg-canvas text-[13px] text-brand-200 ring-1 ring-white/10 transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15" aria-label="Geser peta ke selatan">↓</button>
                    <button type="button" data-point-step="east" class="size-8 rounded-lg bg-canvas text-[13px] text-brand-200 ring-1 ring-white/10 transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15" aria-label="Geser peta ke timur">→</button>

                    <button type="button" data-point-zoom="in" class="flex size-8 items-center justify-center rounded-lg bg-canvas text-brand-200 ring-1 ring-white/10 transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15" aria-label="Perbesar peta">+</button>
                    <button type="button" data-point-zoom="out" class="flex size-8 items-center justify-center rounded-lg bg-canvas text-brand-200 ring-1 ring-white/10 transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15" aria-label="Perkecil peta">−</button>
                </div>

                <div class="mt-2.5">
                    <label for="need-area" class="text-[10px] font-semibold text-muted">Nama area <span class="font-normal">(terisi otomatis)</span></label>

                    <input
                        id="need-area"
                        name="area"
                        type="text"
                        value="{{ old('area') }}"
                        maxlength="120"
                        autocomplete="off"
                        placeholder="Contoh: Sukajadi, Bandung"
                        class="mt-1 h-10 w-full rounded-xl border-0 bg-canvas px-3 text-[12px] text-ink ring-1 ring-white/10 transition placeholder:text-muted/70 focus:bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand-500/40"
                    >
                </div>

                @error('latitude')
                    <p class="mt-1 text-[10px] font-medium text-[#FF9B9B]">{{ $message }}</p>
                @enderror

                @error('longitude')
                    <p class="mt-1 text-[10px] font-medium text-[#FF9B9B]">{{ $message }}</p>
                @enderror

                @error('area')
                    <p class="mt-1 text-[10px] font-medium text-[#FF9B9B]">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="need-location" class="text-[11px] font-semibold text-brand-200">Patokan lokasi <span class="font-normal text-muted">(opsional)</span></label>

                <div class="relative mt-1.5">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-muted">
                        @include('partials.icon', ['name' => 'pin', 'class' => 'size-4'])
                    </span>

                    <input
                        id="need-location"
                        name="location"
                        type="text"
                        value="{{ old('location') }}"
                        maxlength="120"
                        autocomplete="off"
                        placeholder="Contoh: Jl. Sukajadi, RW 05"
                        class="h-11 w-full rounded-xl border-0 bg-canvas pl-10 pr-3 text-[12.5px] text-ink ring-1 ring-white/10 transition placeholder:text-muted/70 focus:bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand-500/40"
                    >
                </div>

                @error('location')
                    <p class="mt-1 text-[10px] font-medium text-[#FF9B9B]">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="need-description" class="text-[11px] font-semibold text-brand-200">Ceritakan singkat <span class="font-normal text-muted">(opsional)</span></label>

                <textarea
                    id="need-description"
                    name="description"
                    rows="3"
                    maxlength="600"
                    placeholder="Apa yang warga butuhkan, dan kenapa?"
                    class="mt-1.5 w-full resize-none rounded-xl border-0 bg-canvas px-3 py-2.5 text-[12.5px] leading-relaxed text-ink ring-1 ring-white/10 transition placeholder:text-muted/70 focus:bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand-500/40"
                >{{ old('description') }}</textarea>

                @error('description')
                    <p class="mt-1 text-[10px] font-medium text-[#FF9B9B]">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-start gap-2.5 rounded-2xl bg-canvas px-3.5 py-3 ring-1 ring-white/5">
                @include('partials.icon', ['name' => 'info', 'class' => 'mt-0.5 size-4 shrink-0 text-brand-300'])

                <p class="text-[10.5px] leading-relaxed text-muted">
                    Kebutuhan tersimpan di database dan langsung muncul di Jelajahi serta Laporan milikmu.
                </p>
            </div>

            <div class="flex gap-2.5">
                <button
                    type="button"
                    data-submit-close
                    class="inline-flex h-11 flex-1 items-center justify-center rounded-xl bg-canvas px-4 text-[12px] font-semibold text-brand-200 ring-1 ring-white/10 transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15"
                >
                    Batal
                </button>

                <button
                    type="submit"
                    class="inline-flex h-11 flex-[1.6] items-center justify-center gap-2 rounded-xl bg-brand-700 px-4 text-[12px] font-semibold text-canvas shadow-[0_12px_24px_-16px_rgba(0,0,0,0.8)] transition hover:bg-brand-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20"
                >
                    Kirim kebutuhan
                    @include('partials.icon', ['name' => 'arrow-right', 'class' => 'size-4'])
                </button>
            </div>
        </form>
    </div>
</div>
