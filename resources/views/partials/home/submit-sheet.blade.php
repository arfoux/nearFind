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

            <div class="flex items-start gap-2.5 rounded-2xl bg-canvas px-3.5 py-3 ring-1 ring-white/5">
                @include('partials.icon', ['name' => 'info', 'class' => 'mt-0.5 size-4 shrink-0 text-brand-300'])

                <p class="text-[10.5px] leading-relaxed text-muted">
                    Kebutuhan akan tersimpan di akunmu dan tampil di halaman profil.
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
