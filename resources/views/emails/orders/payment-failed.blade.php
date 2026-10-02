<x-mail::message>
# Payment didn't go through

@if ($recipientName)
Hi {{ $recipientName }},
@else
Hi there,
@endif

We weren't able to process payment for order **{{ $order->order_number }}** (${{ number_format((float) $order->total, 2) }}). Nothing has been charged, and your items are still saved — you can pick up right where you left off.

@if ($order->user)
<x-mail::button :url="route('dashboard')">
Review your order
</x-mail::button>
@else
Return to BuyAndMarket and try checking out again to complete your purchase.
@endif

If you keep running into trouble, reply to this email and we'll help sort it out.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
