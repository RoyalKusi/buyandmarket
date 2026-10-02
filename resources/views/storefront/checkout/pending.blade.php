<x-layouts.storefront title="Checkout — Payment pending">
    <div class="mx-auto max-w-[480px] px-4 md:px-6 py-16 text-center">
        <h1 class="text-heading-lg text-slate-900">Complete your payment</h1>
        <p class="mt-4 text-body-lg text-slate-600">
            {{ $instructions ?? 'Follow the instructions from your payment provider to complete this order.' }}
        </p>
        <p class="mt-4 text-body-sm text-slate-500">We'll confirm your order automatically once payment is received.</p>
    </div>
</x-layouts.storefront>
