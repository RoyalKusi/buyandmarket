<x-mail::message>
# Your seller application needs attention

Hi {{ $seller->user->name }},

We reviewed **{{ $seller->business_name }}**'s application and couldn't approve it this time.

**Reason:** {{ $reasonCode }}
@if ($note)

{{ $note }}
@endif

Please update your KYC documents and resubmit for review — we're glad to take another look.

<x-mail::button :url="route('dashboard.become-seller')">
Update your documents
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
