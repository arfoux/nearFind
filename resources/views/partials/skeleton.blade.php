{{--
    The shape of every tab, drawn before any data arrives.

    Rendered by the server inside the shell (so the first paint of a tab is its
    skeleton, not a blank column) and reused verbatim by resources/js/app.js,
    which injects the matching one the instant a tab is pressed. Keep the
    `nf-sk` building blocks below identical on both sides: they are what makes
    the skeleton and the real page line up.

    @param string $tab  peta|jelajahi|laporan|profil
--}}
@php
    $tab = $tab ?? 'peta';
@endphp

{{-- A skeleton for the map: the surface itself is already painted by CSS, the
     chips and the bottom card are the shapes that arrive with the data. --}}
@if ($tab === 'peta')
    <div class="relative min-h-0 flex-1 overflow-hidden" data-skeleton-pane="peta">
        <div class="pointer-events-none absolute inset-x-0 top-0 z-[5] px-3 pt-3 sm:px-4 lg:px-5 lg:pt-4">
            <div class="nf-glass-strong nf-header rounded-[22px] px-3.5 py-3 sm:px-4 lg:px-5 lg:py-3.5">
                <div class="flex items-center gap-3">
                    <div class="nf-sk nf-sk--avatar size-9 rounded-2xl"></div>
                    <div class="nf-sk nf-sk--line h-10 flex-1 rounded-2xl"></div>
                </div>

                <div class="mt-3 flex gap-2 overflow-hidden">
                    <div class="nf-sk nf-sk--chip h-8 w-20 rounded-full"></div>
                    <div class="nf-sk nf-sk--chip h-8 w-24 rounded-full"></div>
                    <div class="nf-sk nf-sk--chip h-8 w-16 rounded-full"></div>
                    <div class="nf-sk nf-sk--chip h-8 w-24 rounded-full"></div>
                </div>
            </div>
        </div>

        <div class="nf-glass-strong absolute inset-x-3 bottom-[calc(4.75rem+env(safe-area-inset-bottom))] z-[6] rounded-[20px] p-4 lg:inset-x-auto lg:bottom-24 lg:right-5 lg:w-[390px]">
            <div class="flex items-center gap-3">
                <div class="nf-sk nf-sk--avatar size-10 rounded-[14px]"></div>
                <div class="min-w-0 flex-1 space-y-2">
                    <div class="nf-sk nf-sk--line h-2 w-24 rounded-full"></div>
                    <div class="nf-sk nf-sk--line h-3 w-40 rounded-full"></div>
                </div>
                <div class="nf-sk nf-sk--chip h-8 w-16 rounded-full"></div>
            </div>

            <div class="mt-4 space-y-2">
                <div class="nf-sk nf-sk--line h-3 w-full rounded-full"></div>
                <div class="nf-sk nf-sk--line h-3 w-11/12 rounded-full"></div>
                <div class="nf-sk nf-sk--line h-3 w-2/3 rounded-full"></div>
            </div>

            <div class="nf-sk nf-sk--card mt-4 h-14 rounded-2xl"></div>
        </div>
    </div>
@else
    <div class="min-h-0 flex-1 overflow-hidden px-4 pb-6 pt-5 sm:px-6 lg:px-8" data-skeleton-pane="{{ $tab }}" aria-hidden="true">
        <div class="mx-auto w-full max-w-7xl">
            <div class="nf-sk nf-sk--line h-2.5 w-20 rounded-full"></div>
            <div class="nf-sk nf-sk--line mt-2.5 h-6 w-56 rounded-lg"></div>
            <div class="nf-sk nf-sk--line mt-2.5 h-3.5 w-full max-w-[420px] rounded-full"></div>

            @if ($tab === 'jelajahi')
                <div class="nf-sk nf-sk--card mt-4 h-11 rounded-2xl"></div>

                <div class="mt-3 flex gap-2 overflow-hidden">
                    <div class="nf-sk nf-sk--chip h-8 w-16 rounded-full"></div>
                    <div class="nf-sk nf-sk--chip h-8 w-24 rounded-full"></div>
                    <div class="nf-sk nf-sk--chip h-8 w-20 rounded-full"></div>
                    <div class="nf-sk nf-sk--chip h-8 w-24 rounded-full"></div>
                </div>

                <div class="mt-4 space-y-2.5">
                    @foreach ([1, 2, 3, 4] as $row)
                        <div class="nf-sk nf-sk--card rounded-[24px] p-4">
                            <div class="flex items-start gap-3">
                                <div class="nf-sk nf-sk--avatar size-10 rounded-xl"></div>
                                <div class="min-w-0 flex-1 space-y-2">
                                    <div class="nf-sk nf-sk--line h-2 w-16 rounded-full"></div>
                                    <div class="nf-sk nf-sk--line h-3.5 w-3/4 rounded-full"></div>
                                    <div class="nf-sk nf-sk--line h-3 w-1/2 rounded-full"></div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @elseif ($tab === 'laporan')
                <div class="mt-4 grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                    @foreach ([1, 2, 3, 4, 5, 6] as $cell)
                        <div class="nf-sk nf-sk--card rounded-2xl p-3.5">
                            <div class="nf-sk nf-sk--line h-2 w-14 rounded-full"></div>
                            <div class="nf-sk nf-sk--line mt-2 h-5 w-10 rounded-full"></div>
                        </div>
                    @endforeach
                </div>

                <div class="nf-sk nf-sk--card mt-4 rounded-[24px] p-4">
                    <div class="nf-sk nf-sk--line h-3 w-28 rounded-full"></div>

                    <div class="mt-3 space-y-2.5">
                        @foreach ([1, 2, 3, 4] as $bar)
                            <div class="flex items-center gap-2.5">
                                <div class="nf-sk nf-sk--line h-2.5 w-20 rounded-full"></div>
                                <div class="nf-sk nf-sk--chip h-2.5 w-full rounded-full" style="max-width: {{ 100 - ($bar * 14) }}%"></div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-4 space-y-2.5">
                    @foreach ([1, 2, 3] as $row)
                        <div class="nf-sk nf-sk--card h-20 rounded-[24px]"></div>
                    @endforeach
                </div>
            @else
                <div class="nf-sk nf-sk--card mt-5 h-16 rounded-[26px]"></div>

                <div class="nf-sk nf-sk--card mt-4 rounded-[26px] p-5">
                    <div class="nf-sk nf-sk--avatar size-12 rounded-2xl"></div>
                    <div class="nf-sk nf-sk--line mt-4 h-4 w-40 rounded-lg"></div>
                    <div class="nf-sk nf-sk--line mt-2.5 h-3 w-full max-w-[320px] rounded-full"></div>
                    <div class="mt-5 flex gap-2">
                        <div class="nf-sk nf-sk--chip h-11 flex-1 rounded-xl"></div>
                        <div class="nf-sk nf-sk--chip h-11 flex-1 rounded-xl"></div>
                    </div>
                </div>

                <div class="mt-4 space-y-2.5">
                    @foreach ([1, 2, 3] as $row)
                        <div class="nf-sk nf-sk--card h-[68px] rounded-2xl"></div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endif
