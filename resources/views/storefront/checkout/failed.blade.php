<x-layouts.storefront title="Checkout — Payment failed">
    {{-- Design System §6.6: "payment failure recovery: dedicated state
         (not a silent redirect) ... order held for 24h, Try again
         retains the full cart/address/method selection so nothing is
         re-entered." checkout_sessions keeps everything collected so
         far; retrying just re-attempts payment. --}}
    <div class="mx-auto max-w-[480px] px-4 md:px-6 py-16 text-center">
        <h1 class="text-heading-lg text-slate-900">Payment didn't go through</h1>
        <p class="mt-4 text-body-lg text-slate-600">Your cart, address and delivery selection are still saved — you can try again without re-entering anything.</p>
        <a href="{{ route('storefront.checkout.payment', $checkoutSession) }}" class="mt-6 inline-block h-12 leading-[48px] px-6 rounded-sm bg-blue-600 text-slate-0 text-button font-semibold hover:bg-blue-500">
            Try again
        </a>
    </div>
</x-layouts.storefront>
