<x-emails.layout preheader="Confirm your email address to finish setting up your account" :message="$message">
    <h1 style="margin:0 0 12px; font-family:'Space Grotesk', Arial, Helvetica, sans-serif; font-size:24px; line-height:30px; font-weight:700; letter-spacing:-0.01em; color:#131926;">
        Confirm your email address
    </h1>

    <p style="margin:0 0 4px; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:15px; line-height:24px; color:#363F52;">
        Welcome to {{ config('app.name') }} — please confirm this is your email address to finish setting up your account and start buying or selling.
    </p>

    <x-emails.button :url="$url">Verify email address</x-emails.button>

    <p style="margin:28px 0 0; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:14px; line-height:22px; color:#66718A;">
        If the button above doesn't work, copy and paste this link into your browser:<br>
        <a href="{{ $url }}" style="color:#1F53B5; word-break:break-all;">{{ $url }}</a>
    </p>

    <p style="margin:16px 0 0; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:14px; line-height:22px; color:#66718A;">
        If you didn't create an account, no further action is required.
    </p>
</x-emails.layout>
