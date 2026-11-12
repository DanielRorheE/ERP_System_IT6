<header class="border-b border-stone-200 bg-white">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-8">
        <a class="flex items-center gap-3" href="{{ route('dashboard') }}">
            <img
                class="h-10 w-10 rounded-lg border border-orange-100 object-cover shadow-sm"
                src="{{ asset('images/out-of-boredom-logo.png') }}"
                alt="OUT OF BOREDOM logo"
            >
            <span class="grid">
                <strong class="font-[Manrope,sans-serif] text-sm font-extrabold tracking-tight text-stone-900">OUT OF BOREDOM</strong>
                <small class="text-[9px] font-bold tracking-[0.16em] text-[#c85a1c]">BUSINESS OWNER OVERVIEW</small>
            </span>
        </a>
        <div class="flex items-center gap-3">
            <a class="rounded-lg bg-[#f07838] px-3 py-2 text-xs font-semibold text-white hover:bg-[#dc6328]" href="{{ route('business-input') }}">Add business data</a>
            <span class="hidden text-xs text-stone-500 sm:inline">{{ Auth::user()->email }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="rounded-lg border border-orange-200 px-3 py-2 text-xs font-semibold text-stone-700 hover:border-orange-300 hover:bg-orange-50" type="submit">Sign out</button>
            </form>
        </div>
    </div>
</header>
