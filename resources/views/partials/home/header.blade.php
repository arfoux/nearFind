@php
    // Signed-in: the header opens the account page. Guest: it is the sign-in
    // link, so it goes straight to /auth instead of bouncing off /profile.
    $accountUrl = $sealUser ? route('profile') : route('login');
@endphp

{{-- R-10/R-31: one of exactly two glass hosts. Refraction bends the map, Gaussian
     blur frosts its labels, and a top light angle models the rim. The field stays solid. --}}
<header class="nf-header pointer-events-auto relative z-[700]">
    <lg-panel tone="light" radius="20" refraction="28" bezel="0.55" curvature="4" chroma="0.17" blur="4.5" specular="0.95" specular-width="2.2" light-angle="-90" class="px-3.5 py-3 sm:px-4 lg:px-5 lg:py-3.5">
        <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-3 lg:grid-cols-[auto_minmax(0,1fr)_auto] lg:gap-x-8">
            @include('partials.brand', ['class' => 'col-start-1 row-start-1 justify-self-start'])

            @if ($sealUser)
                <a
                    href="{{ $accountUrl }}"
                    data-app-link
                    class="col-start-2 row-start-1 inline-flex h-11 max-w-[150px] items-center justify-center gap-2 rounded-full bg-brand-50 px-2.5 text-[11.5px] font-semibold text-brand-200 ring-1 ring-white/10 transition hover:bg-brand-100 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20 lg:col-start-3 lg:h-11 lg:px-3.5 lg:text-xs"
                    aria-label="Buka profil {{ $sealUser->name }}"
                >
                    <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-700 text-[9px] font-bold text-white">
                        {{ mb_strtoupper(mb_substr($sealUser->name, 0, 1)) }}
                    </span>
                    <span class="hidden truncate sm:inline">{{ $sealUser->name }}</span>
                </a>
            @else
                <a
                    href="{{ $accountUrl }}"
                    data-app-link
                    class="col-start-2 row-start-1 inline-flex h-11 items-center justify-center gap-1.5 rounded-full bg-surface px-3.5 text-[11.5px] font-semibold text-brand-200 shadow-sm ring-1 ring-white/10 transition hover:bg-surface-2 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20 active:scale-[0.98] lg:col-start-3 lg:h-11 lg:px-4 lg:text-xs"
                >
                    @include('partials.icon', ['name' => 'user', 'class' => 'size-3.5'])
                    Masuk
                </a>
            @endif

            <div class="col-span-2 row-start-2 min-w-0 lg:col-span-1 lg:col-start-2 lg:row-start-1">
                @include('partials.home.search')
            </div>
        </div>
    </lg-panel>
</header>
