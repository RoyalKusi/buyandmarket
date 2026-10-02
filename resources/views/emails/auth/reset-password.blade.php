<x-emails.layout preheader="Reset your password — this link expires soon" :message="$message">
    <h1 style="margin:0 0 12px; font-family:'Space Grotesk', Arial, Helvetica, sans-serif; font-size:24px; line-height:30px; font-weight:700; letter-spacing:-0.01em; color:#131926;">
        Reset your password
    </h1>

    <p style="margin:0 0 4px; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:15px; line-height:24px; color:#363F52;">
        You are receiving this email because we received a password reset request for your {{ config('app.name') }} account.
    </p>

    <x-emails.button :url="$url">Reset password</x-emails.button>

    <p style="margin:28px 0 0; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:14px; line-height:22px; color:#66718A;">
        This password reset link will expire in {{ $expireMinutes }} minutes.
    </p>

    <p style="margin:16px 0 0; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:14px; line-height:22px; color:#66718A;">
        If you did not request a password reset, no further action is required.
    </p>
</x-emails.layout>
