<div id="login-gate" class="fixed inset-0 z-[1300] hidden items-end justify-center lg:items-center" role="presentation">
    <div data-login-backdrop class="absolute inset-0 bg-black/60 backdrop-blur-[2px]"></div>

    <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="login-gate-title"
        aria-describedby="login-gate-description"
        class="login-gate-panel relative z-10 w-full max-w-[560px] rounded-t-[28px] bg-surface px-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-3 shadow-[0_-24px_70px_-30px_rgba(0,0,0,0.55)] lg:max-w-[430px] lg:rounded-[28px] lg:p-6"
    >
        <div class="mx-auto mb-4 h-1 w-10 rounded-full bg-white/10 lg:hidden"></div>

        <div class="flex items-start gap-3">
            <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-200 ring-1 ring-white/10">
                @include('partials.icon', ['name' => 'user', 'class' => 'size-6'])
            </span>

            <div class="min-w-0 flex-1">
                <p class="text-[9px] font-semibold uppercase tracking-[0.16em] text-brand-300">Akses warga</p>
                <h2 id="login-gate-title" class="mt-1 text-xl font-semibold tracking-[-0.025em] text-ink">Masuk untuk melanjutkan</h2>
            </div>

            <button
                type="button"
                data-login-close
                class="flex size-9 shrink-0 items-center justify-center rounded-full bg-canvas text-muted transition hover:bg-brand-50 hover:text-brand-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15"
                aria-label="Tutup dialog masuk"
            >
                @include('partials.icon', ['name' => 'close', 'class' => 'size-4'])
            </button>
        </div>

        <p id="login-gate-description" data-login-message class="mt-3 text-[12px] leading-relaxed text-muted">
            Masuk ke akun warga untuk melanjutkan.
        </p>

        <div class="mt-4 flex items-start gap-2.5 rounded-2xl bg-canvas px-3.5 py-3 ring-1 ring-white/5">
            @include('partials.icon', ['name' => 'info', 'class' => 'mt-0.5 size-4 shrink-0 text-brand-300'])

            <p class="text-[10.5px] leading-relaxed text-muted">
                Dukungan dan laporan terhubung ke identitas warga supaya informasi tetap tepercaya.
            </p>
        </div>

        <div class="mt-5 flex gap-2.5">
            <button
                type="button"
                data-login-close
                class="inline-flex h-11 flex-1 items-center justify-center rounded-xl bg-canvas px-4 text-[12px] font-semibold text-brand-200 ring-1 ring-white/10 transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15"
            >
                Nanti saja
            </button>

            <a
                href="{{ route('login') }}"
                data-login-submit
                class="inline-flex h-11 flex-[1.35] items-center justify-center gap-2 rounded-xl bg-brand-700 px-4 text-[12px] font-semibold text-canvas shadow-[0_12px_24px_-16px_rgba(0,0,0,0.8)] transition hover:bg-brand-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20"
            >
                Masuk
                @include('partials.icon', ['name' => 'arrow-right', 'class' => 'size-4'])
            </a>
        </div>
    </div>
</div>
