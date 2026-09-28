@props(['title' => 'BuyAndMarket', 'description' => "Zimbabwe's multi-vendor digital marketplace."])
<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full flex flex-col">
    {{--
        Design System §4.5 desktop header (72px, sticky, elevation-1 on
        scroll). Omitted here (flagged in CHANGELOG.md, Run 1.4): the
        category mega-menu, AI-assistant entry icon, and the account/
        wishlist/cart icon cluster — cart and wishlist have no backing
        module yet (Run 1.5/§3.5), and a fake, non-functional icon would
        be worse than none. The mobile bottom tab bar (§4.5) is omitted
        for the same reason: 3 of its 5 items (AI, cart, account actions)
        aren't real yet.
    --}}
    <header class="sticky top-0 z-40 bg-slate-0 border-b border-slate-100">
        <div class="mx-auto max-w-[1280px] px-4 md:px-6 h-[72px] flex items-center gap-6">
            <a href="{{ route('storefront.home') }}" class="shrink-0 text-heading-md font-display text-blue-600" aria-label="BuyAndMarket home">
                BuyAndMarket
            </a>

            <form action="{{ route('storefront.search') }}" method="GET" class="flex-1 max-w-[640px]">
                <label for="storefront-search" class="sr-only">Search products</label>
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M21 21l-4.3-4.3m1.8-5.2a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        id="storefront-search"
                        type="search"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Search products, stores..."
                        class="w-full h-11 rounded-full bg-slate-50 pl-11 pr-4 text-body-lg text-slate-900 placeholder:text-slate-400 border border-transparent focus:bg-slate-0 focus:border-blue-600 focus:outline-none transition-colors duration-fast"
                    >
                </div>
            </form>

            <nav aria-label="Account" class="shrink-0 text-body-md">
                @auth
                    <a href="{{ route('dashboard') }}" class="text-slate-600 hover:text-slate-900">My account</a>
                @else
                    <a href="{{ route('login') }}" class="text-slate-600 hover:text-slate-900">Sign in</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="flex-1">
        {{ $slot }}
    </main>

    {{-- Design System §6.1 step 10: full 4-column footer, newsletter
         signup and payment-method icons are deferred — this is a minimal,
         honest footer rather than a fabricated one. --}}
    <footer class="border-t border-slate-100 bg-slate-0 mt-16">
        <div class="mx-auto max-w-[1280px] px-4 md:px-6 py-10 text-body-sm text-slate-500">
            <p>&copy; {{ now()->year }} BuyAndMarket. Buy more. We deliver.</p>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
