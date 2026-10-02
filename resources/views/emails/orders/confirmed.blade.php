<x-mail::message>
# Order confirmed

@if ($recipientName)
Hi {{ $recipientName }},
@else
Hi there,
@endif

Thanks for shopping with BuyAndMarket — your payment for order **{{ $order->order_number }}** went through and your order is now confirmed.

@foreach ($orderGroups as $group)
<x-mail::table>
| Item | Qty | Price |
| :--- | :-: | ----: |
@foreach ($group->items as $item)
| {{ $item->variant->product->title }}{{ $item->variant->sku ? " ({$item->variant->sku})" : '' }} | {{ $item->quantity }} | ${{ number_format((float) $item->price_at_purchase, 2) }} |
@endforeach
</x-mail::table>

Subtotal: **${{ number_format((float) $group->subtotal, 2) }}** &nbsp; Delivery: **${{ number_format((float) $group->delivery_fee, 2) }}**

---
@endforeach

**Order total: ${{ number_format((float) $order->total, 2) }}**

@if ($order->user)
<x-mail::button :url="route('dashboard.orders.show', $order)">
View your order
</x-mail::button>
@endif

We'll let you know as soon as your order ships.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
