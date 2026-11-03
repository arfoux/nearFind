<x-layout title="Dasbor · nearFind" active="profil">
    <div class="min-h-0 flex-1 overflow-y-auto px-4 pb-6 pt-5 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-[640px]">
            <div class="flex items-center gap-3">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-brand-700 text-lg font-semibold text-white shadow-[0_14px_28px_-18px_rgba(0,92,85,0.9)]">
                    {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                </span>

                <div class="min-w-0">
                    <p class="text-[9px] font-semibold uppercase tracking-[0.16em] text-brand-600">Akun warga</p>
                    <h1 class="truncate text-xl font-semibold tracking-[-0.03em] text-ink">Halo, {{ $user->name }}</h1>
                    <p class="truncate text-[11px] font-medium text-muted">{{ $user->email }}</p>
                </div>
            </div>

            <section class="mt-5 overflow-hidden rounded-[28px] bg-white p-5 shadow-[0_22px_60px_-40px_rgba(0,60,55,0.45)] ring-1 ring-brand-900/10">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-2.5 py-1 text-[9px] font-semibold uppercase tracking-[0.12em] text-brand-700">
                        <x-icon name="check" class="size-3" />
                        stateless-seal
                    </span>

                    <span class="inline-flex items-center gap-1.5 rounded-full bg-canvas px-2.5 py-1 text-[9px] font-semibold uppercase tracking-[0.12em] text-muted">
                        v1
                    </span>
                </div>

                <h2 class="mt-3 text-[20px] font-semibold tracking-[-0.03em] text-ink">Sesi aktif tanpa database</h2>

                <p class="mt-1.5 text-[12px] leading-relaxed text-muted">
                    Status masukmu dibawa oleh cookie berisi token stseal.v1 yang dienkripsi AES-256-GCM. Server tidak menyimpan baris sesi atau akun di database.
                </p>

                <dl class="mt-4 grid grid-cols-2 gap-2.5">
                    <div class="rounded-2xl bg-canvas px-3.5 py-3 ring-1 ring-brand-900/5">
                        <dt class="text-[9px] font-semibold uppercase tracking-[0.14em] text-muted">Guard</dt>
                        <dd class="mt-1 text-[12px] font-semibold text-ink">seal</dd>
                    </div>

                    <div class="rounded-2xl bg-canvas px-3.5 py-3 ring-1 ring-brand-900/5">
                        <dt class="text-[9px] font-semibold uppercase tracking-[0.14em] text-muted">Penyimpanan akun</dt>
                        <dd class="mt-1 text-[12px] font-semibold text-ink">Tanpa database</dd>
                    </div>

                    <div class="rounded-2xl bg-canvas px-3.5 py-3 ring-1 ring-brand-900/5">
                        <dt class="text-[9px] font-semibold uppercase tracking-[0.14em] text-muted">Algoritma</dt>
                        <dd class="mt-1 text-[12px] font-semibold text-ink">AES-256-GCM</dd>
                    </div>

                    <div class="rounded-2xl bg-canvas px-3.5 py-3 ring-1 ring-brand-900/5">
                        <dt class="text-[9px] font-semibold uppercase tracking-[0.14em] text-muted">Cookie</dt>
                        <dd class="mt-1 text-[12px] font-semibold text-ink">HttpOnly</dd>
                    </div>
                </dl>

                <form method="POST" action="{{ route('logout') }}" class="mt-4">
                    @csrf

                    <button
                        type="submit"
                        class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-[#FFF1F1] px-4 text-[12px] font-semibold text-[#9F1D1D] ring-1 ring-[#9F1D1D]/10 transition hover:bg-[#FFE4E4] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#9F1D1D]/15"
                    >
                        <x-icon name="log-out" class="size-4" />
                        Keluar dari akun
                    </button>
                </form>
            </section>

            <a
                href="{{ route('home') }}"
                class="mt-4 inline-flex items-center gap-1.5 rounded-lg px-1 py-1 text-[11.5px] font-semibold text-brand-700 transition hover:text-brand-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15"
            >
                <x-icon name="map" class="size-3.5" />
                Kembali ke peta
            </a>
        </div>
    </div>
</x-layout>
