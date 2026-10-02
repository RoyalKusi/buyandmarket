<x-emails.layout :preheader="'Your seller application needs attention — ' . $reasonCode">
    <x-emails.badge tone="danger">Application needs attention</x-emails.badge>

    <h1 style="margin:0 0 12px; font-family:'Space Grotesk', Arial, Helvetica, sans-serif; font-size:24px; line-height:30px; font-weight:700; letter-spacing:-0.01em; color:#131926;">
        Your seller application needs attention
    </h1>

    <p style="margin:0 0 4px; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:15px; line-height:24px; color:#363F52;">
        Hi {{ $seller->user->name }},
    </p>

    <p style="margin:0 0 20px; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:15px; line-height:24px; color:#363F52;">
        We reviewed <strong style="color:#131926;">{{ $seller->business_name }}</strong>'s application and couldn't approve it this time.
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
        Please update your KYC documents and resubmit for review — we're glad to take another look.
    </p>

    <x-emails.button :url="route('dashboard.become-seller')">Update your documents</x-emails.button>
</x-emails.layout>
