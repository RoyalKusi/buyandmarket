@props(['title' => 'Dashboard', 'active' => ''])
<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — BuyAndMarket</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50">
    @php($user = auth()->user())
    {{--
        Design System §6.11: all dashboards share one shell — left
        sidebar nav, top bar, n-50 background with n-0 card panels — so
        a buyer who is also a seller/shipper/admin sees one product, not
        several bolted together. The seller-mode sidebar-inverts-to-
        blue-900 treatment and the icon-rail collapse are deferred
        (flagged in CHANGELOG.md, Run 1.7) — this is a functional, not
        yet pixel-complete, home base for each role.
    --}}
    <div class="min-h-full flex">
        <aside class="w-[240px] shrink-0 bg-slate-0 border-r border-slate-100 hidden md:flex md:flex-col">
            <a href="{{ route('storefront.home') }}" class="h-[72px] flex items-center px-6 text-heading-sm font-display text-blue-600">
                BuyAndMarket
            </a>

            <nav class="flex-1 px-3 py-4 space-y-6 text-body-md">
                <div>
                    <p class="px-3 text-caption uppercase tracking-wide text-slate-400 mb-2">My account</p>
                    <a href="{{ route('dashboard') }}" class="block rounded-sm px-3 py-2 {{ $active === 'buyer' ? 'bg-blue-50 text-blue-600' : 'text-slate-600 hover:bg-slate-50' }}">Orders</a>
                    <a href="{{ route('dashboard.addresses') }}" class="block rounded-sm px-3 py-2 {{ $active === 'addresses' ? 'bg-blue-50 text-blue-600' : 'text-slate-600 hover:bg-slate-50' }}">Addresses</a>
                    <a href="{{ route('dashboard.assistant') }}" class="block rounded-sm px-3 py-2 {{ $active === 'assistant' ? 'bg-blue-50 text-blue-600' : 'text-slate-600 hover:bg-slate-50' }}">Ask BM Assistant</a>
                    <a href="{{ route('dashboard.security') }}" class="block rounded-sm px-3 py-2 {{ $active === 'security' ? 'bg-blue-50 text-blue-600' : 'text-slate-600 hover:bg-slate-50' }}">Security</a>
                </div>

                @if ($user?->seller)
                    <div>
                        <p class="px-3 text-caption uppercase tracking-wide text-slate-400 mb-2">Seller</p>
                        <a href="{{ route('seller.dashboard.index') }}" class="block rounded-sm px-3 py-2 {{ $active === 'seller.overview' ? 'bg-blue-50 text-blue-600' : 'text-slate-600 hover:bg-slate-50' }}">Overview</a>
                        <a href="{{ route('seller.dashboard.products') }}" class="block rounded-sm px-3 py-2 {{ $active === 'seller.products' ? 'bg-blue-50 text-blue-600' : 'text-slate-600 hover:bg-slate-50' }}">Products</a>
                        <a href="{{ route('seller.dashboard.orders') }}" class="block rounded-sm px-3 py-2 {{ $active === 'seller.orders' ? 'bg-blue-50 text-blue-600' : 'text-slate-600 hover:bg-slate-50' }}">Orders</a>
                        <a href="{{ route('seller.dashboard.delivery') }}" class="block rounded-sm px-3 py-2 {{ $active === 'seller.delivery' ? 'bg-blue-50 text-blue-600' : 'text-slate-600 hover:bg-slate-50' }}">Delivery</a>
                    </div>
                @endif

                @if ($user?->shipper)
                    <div>
                        <p class="px-3 text-caption uppercase tracking-wide text-slate-400 mb-2">Shipper</p>
                        <a href="{{ route('shipper.dashboard.index') }}" class="block rounded-sm px-3 py-2 {{ $active === 'shipper' ? 'bg-blue-50 text-blue-600' : 'text-slate-600 hover:bg-slate-50' }}">Deliveries</a>
                    </div>
                @endif

                @if ($user?->hasRole('admin'))
                    <div>
                        <p class="px-3 text-caption uppercase tracking-wide text-slate-400 mb-2">Admin</p>
                        <a href="{{ route('admin.dashboard.sellers') }}" class="block rounded-sm px-3 py-2 {{ $active === 'admin.sellers' ? 'bg-blue-50 text-blue-600' : 'text-slate-600 hover:bg-slate-50' }}">Seller approvals</a>
                        <a href="{{ route('admin.dashboard.products') }}" class="block rounded-sm px-3 py-2 {{ $active === 'admin.products' ? 'bg-blue-50 text-blue-600' : 'text-slate-600 hover:bg-slate-50' }}">Product moderation</a>
                        <a href="{{ route('admin.dashboard.audit-log') }}" class="block rounded-sm px-3 py-2 {{ $active === 'admin.audit' ? 'bg-blue-50 text-blue-600' : 'text-slate-600 hover:bg-slate-50' }}">Audit log</a>
                        <a href="{{ route('admin.dashboard.ai-monitoring') }}" class="block rounded-sm px-3 py-2 {{ $active === 'admin.ai' ? 'bg-blue-50 text-blue-600' : 'text-slate-600 hover:bg-slate-50' }}">AI monitoring</a>
                    </div>
                @endif
            </nav>

            <form method="POST" action="{{ route('logout') }}" class="p-3 border-t border-slate-100">
                @csrf
                <button type="submit" class="w-full text-left rounded-sm px-3 py-2 text-body-md text-slate-600 hover:bg-slate-50">Sign out</button>
            </form>
        </aside>

        <div class="flex-1 min-w-0">
            <header class="h-[72px] bg-slate-0 border-b border-slate-100 flex items-center justify-between px-6">
                <h1 class="text-heading-md font-display text-slate-900">{{ $title }}</h1>
                <span class="text-body-sm text-slate-500">{{ $user?->name }}</span>
            </header>

            <main class="p-6">
                @if (session('status'))
                    <div class="mb-4 rounded-sm border border-green-600 bg-green-50 p-3 text-body-sm text-green-700">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-4 rounded-sm border border-red-600 bg-red-50 p-3 text-body-sm text-red-700">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
