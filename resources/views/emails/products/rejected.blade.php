@php($preheader = $product->title.' needs changes before it can go live')
<x-emails.layout :preheader="$preheader">
    <x-emails.badge tone="danger">Changes needed</x-emails.badge>

    <h1 style="margin:0 0 12px; font-family:'Space Grotesk', Arial, Helvetica, sans-serif; font-size:24px; line-height:30px; font-weight:700; letter-spacing:-0.01em; color:#131926;">
        Changes needed on your listing
    </h1>

    <p style="margin:0 0 4px; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:15px; line-height:24px; color:#363F52;">
        Hi {{ $product->store->seller->user->name }},
    </p>

    <p style="margin:0 0 20px; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:15px; line-height:24px; color:#363F52;">
        We reviewed <strong style="color:#131926;">{{ $product->title }}</strong> and it needs some changes before it can go live.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px; background-color:#FDECEE; border-radius:8px;">
        <tr>
            <td style="padding:16px 20px; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:14px; line-height:22px; color:#9E0C24;">
                <strong>Reason:</strong> {{ $reasonCode }}
                @if ($note)
                    <br><br>{{ $note }}
                @endif
            </td>
        </tr>
    </table>

    <p style="margin:0 0 4px; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:15px; line-height:24px; color:#363F52;">
        Your listing has been moved back to drafts — make the changes and submit it for review again.
    </p>

    <x-emails.button :url="route('seller.dashboard.products')">Edit your listing</x-emails.button>
</x-emails.layout>
