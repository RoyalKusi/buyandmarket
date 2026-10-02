@php($preheader = $product->title.' passed review and is now live')
<x-emails.layout :preheader="$preheader" :message="$message">
    <x-emails.badge tone="success">Listing approved</x-emails.badge>

    <h1 style="margin:0 0 12px; font-family:'Space Grotesk', Arial, Helvetica, sans-serif; font-size:24px; line-height:30px; font-weight:700; letter-spacing:-0.01em; color:#131926;">
        Your listing is live
    </h1>

    <p style="margin:0 0 4px; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:15px; line-height:24px; color:#363F52;">
        Hi {{ $product->store->seller->user->name }},
    </p>

    <p style="margin:0 0 4px; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:15px; line-height:24px; color:#363F52;">
        <strong style="color:#131926;">{{ $product->title }}</strong> passed review and is now published — buyers can find and purchase it right away.
    </p>

    <x-emails.button :url="route('seller.dashboard.products')">View your products</x-emails.button>
</x-emails.layout>
