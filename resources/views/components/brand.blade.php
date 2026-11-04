@props(['tagline' => true, 'badge' => null])

<a
    href="{{ route('home') }}"
    {{ $attributes->merge([
        'class' => 'group inline-flex items-center gap-2.5 rounded-2xl outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20',
        'aria-label' => 'nearFind — kembali ke peta kebutuhan',
    ]) }}
>
    <span class="relative flex size-10 shrink-0 items-center justify-center transition-transform duration-200 group-hover:-rotate-3 group-hover:scale-[1.03] lg:size-[44px]">
        <svg viewBox="0 0 44 44" class="size-full drop-shadow-[0_8px_14px_rgba(0,92,85,0.24)]" aria-hidden="true">
            <rect width="44" height="44" rx="14" fill="#005C55" />
            <path d="M22 8.5c-5.7 0-10.2 4.2-10.2 9.5 0 6.7 10.2 16.1 10.2 16.1s10.2-9.4 10.2-16.1c0-5.3-4.5-9.5-10.2-9.5Z" fill="#FFFFFF" />
            <circle cx="22" cy="18" r="3.7" fill="#FD761A" />
            <circle cx="33" cy="11" r="2.6" fill="#FD761A" />
        </svg>
    </span>

    <span class="flex min-w-0 flex-col leading-none">
        <span class="flex items-center gap-1.5">
            <span class="text-[17px] font-semibold tracking-[-0.035em] text-brand-800 lg:text-[19px]">nearFind</span>
            @if ($badge)
                <span class="rounded-full bg-brand-50 px-1.5 py-0.5 text-[7px] font-bold uppercase tracking-[0.14em] text-brand-700 ring-1 ring-brand-700/10">{{ $badge }}</span>
            @endif
        </span>
        @if ($tagline)
            <span class="mt-1 text-[8.5px] font-semibold uppercase tracking-[0.15em] text-muted lg:text-[9px]">Peta kebutuhan warga</span>
        @endif
    </span>
</a>
