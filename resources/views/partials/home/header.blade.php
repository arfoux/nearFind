<header class="nf-glass-strong nf-header pointer-events-auto relative z-[700] rounded-[22px] px-3.5 py-3 sm:px-4 lg:px-5 lg:py-3.5">
    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-3 lg:grid-cols-[auto_minmax(0,1fr)_auto] lg:gap-x-8">
        @include('partials.brand', ['class' => 'col-start-1 row-start-1 justify-self-start', 'badge' => 'WARGA'])

        @auth('seal')
            @php($sealUser = auth('seal')->user())

            <a
                href="{{ route('profile.index') }}"
                class="col-start-2 row-start-1 inline-flex h-9 max-w-[150px] items-center justify-center gap-2 rounded-full bg-brand-50 px-2.5 text-[11.5px] font-semibold text-brand-800 ring-1 ring-brand-700/10 transition hover:bg-brand-100 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20 lg:col-start-3 lg:h-10 lg:px-3.5 lg:text-xs"
                aria-label="Buka profil {{ $sealUser->name }}"
            >
                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-700 text-[9px] font-bold text-white">
                    {{ mb_strtoupper(mb_substr($sealUser->name, 0, 1)) }}
                </span>
                <span class="hidden truncate sm:inline">{{ $sealUser->name }}</span>
            </a>
        @else
            <a
                href="{{ route('login') }}"
                class="col-start-2 row-start-1 inline-flex h-9 items-center justify-center gap-1.5 rounded-full bg-white/75 px-3.5 text-[11.5px] font-semibold text-brand-800 shadow-sm ring-1 ring-brand-900/10 transition hover:bg-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20 active:scale-[0.98] lg:col-start-3 lg:h-10 lg:px-4 lg:text-xs"
            >
                @include('partials.icon', ['name' => 'user', 'class' => 'size-3.5'])
                Masuk
            </a>
        @endauth

        <div class="col-span-2 row-start-2 min-w-0 lg:col-span-1 lg:col-start-2 lg:row-start-1">
            @include('partials.home.search')
        </div>
    </div>
</header>
