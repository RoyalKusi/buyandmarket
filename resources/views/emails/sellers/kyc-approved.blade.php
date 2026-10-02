<x-emails.layout :preheader="$seller->business_name . ' has been approved — your store is now live'" :message="$message">
    <x-emails.badge tone="success">Seller account approved</x-emails.badge>

    <h1 style="margin:0 0 12px; font-family:'Space Grotesk', Arial, Helvetica, sans-serif; font-size:24px; line-height:30px; font-weight:700; letter-spacing:-0.01em; color:#131926;">
        You're approved!
    </h1>

    <p style="margin:0 0 4px; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:15px; line-height:24px; color:#363F52;">
        Hi {{ $seller->user->name }},
    </p>

    <p style="margin:0 0 4px; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:15px; line-height:24px; color:#363F52;">
        Great news — <strong style="color:#131926;">{{ $seller->business_name }}</strong> has passed review and your seller account is now active. Your store is live and buyers can place orders right away.
    </p>

    <x-emails.button :url="route('seller.dashboard.index')">Go to your seller dashboard</x-emails.button>

    <p style="margin:28px 0 0; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:14px; line-height:22px; color:#66718A;">
        Thanks for selling with {{ config('app.name') }}.
    </p>
</x-emails.layout>
