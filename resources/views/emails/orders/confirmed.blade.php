<x-emails.layout :preheader="'Your order ' . $order->order_number . ' is confirmed — total $' . number_format((float) $order->total, 2)" :message="$message">
    <x-emails.badge tone="success">Order confirmed</x-emails.badge>

    <h1 style="margin:0 0 12px; font-family:'Space Grotesk', Arial, Helvetica, sans-serif; font-size:24px; line-height:30px; font-weight:700; letter-spacing:-0.01em; color:#131926;">
        @if ($recipientName)
            Thanks, {{ $recipientName }} — your order is on its way.
        @else
            Thanks for your order!
        @endif
    </h1>

    <p style="margin:0 0 28px; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:15px; line-height:24px; color:#363F52;">
        Your payment for order <strong style="color:#131926;">{{ $order->order_number }}</strong> went through and your order is now confirmed. Here's what you ordered:
    </p>

    @foreach ($orderGroups as $group)
        <x-emails.order-items :group="$group" />
    @endforeach

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 8px; border-top:2px solid #131926;">
        <tr>
            <td style="padding:16px 16px 0; font-family:'Space Grotesk', Arial, Helvetica, sans-serif; font-size:17px; line-height:24px; font-weight:700; color:#131926;">Order total</td>
            <td align="right" style="padding:16px 16px 0; font-family:'Space Grotesk', Arial, Helvetica, sans-serif; font-size:17px; line-height:24px; font-weight:700; color:#003594;">${{ number_format((float) $order->total, 2) }}</td>
        </tr>
    </table>

    @if ($order->user)
        <x-emails.button :url="route('dashboard.orders.show', $order)">View your order</x-emails.button>
    @endif

    <p style="margin:28px 0 0; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:14px; line-height:22px; color:#66718A;">
        We'll let you know as soon as your order ships. Thanks for shopping with {{ config('app.name') }}.
    </p>
</x-emails.layout>
