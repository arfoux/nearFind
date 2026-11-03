<header class="relative z-[700] shrink-0 bg-canvas px-4 pb-3 pt-3.5 sm:px-5 lg:px-6 lg:pb-4 lg:pt-5">
    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-3 lg:grid-cols-[auto_minmax(0,1fr)_auto] lg:gap-x-8">
        <x-brand class="col-start-1 row-start-1 justify-self-start" />

        <button
            type="button"
            data-action="login"
            class="col-start-2 row-start-1 inline-flex h-9 items-center justify-center rounded-full bg-brand-100 px-4 text-[11.5px] font-semibold text-brand-800 ring-1 ring-brand-700/10 transition hover:bg-brand-200/70 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20 active:scale-[0.98] lg:col-start-3 lg:h-10 lg:px-5 lg:text-xs"
        >
            Masuk
        </button>

        <div class="col-span-2 row-start-2 min-w-0 lg:col-span-1 lg:col-start-2 lg:row-start-1">
            <x-home.search />
        </div>
    </div>
</header>
