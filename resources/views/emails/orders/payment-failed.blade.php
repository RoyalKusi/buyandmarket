<x-emails.layout :preheader="'Payment for order ' . $order->order_number . ' did not go through — nothing was charged'" :message="$message">
    <x-emails.badge tone="danger">Payment not completed</x-emails.badge>

    <h1 style="margin:0 0 12px; font-family:'Space Grotesk', Arial, Helvetica, sans-serif; font-size:24px; line-height:30px; font-weight:700; letter-spacing:-0.01em; color:#131926;">
        Payment didn't go through
    </h1>

    <p style="margin:0 0 4px; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:15px; line-height:24px; color:#363F52;">
        @if ($recipientName)
            Hi {{ $recipientName }},
        @else
            Hi there,
        @endif
    </p>

    <p style="margin:0 0 24px; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:15px; line-height:24px; color:#363F52;">
        We weren't able to process payment for order <strong style="color:#131926;">{{ $order->order_number }}</strong> (${{ number_format((float) $order->total, 2) }}). Nothing has been charged, and your items are still saved — you can pick up right where you left off.
    </p>

    @if ($order->user)
        <x-emails.button :url="route('dashboard')">Review your order</x-emails.button>
    @else
        <p style="margin:0 0 24px; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:15px; line-height:24px; color:#363F52;">
            Return to {{ config('app.name') }} and try checking out again to complete your purchase.
        </p>
    @endif

    <p style="margin:0; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:14px; line-height:22px; color:#66718A;">
        If you keep running into trouble, reply to this email and we'll help sort it out.
    </p>
</x-emails.layout>
