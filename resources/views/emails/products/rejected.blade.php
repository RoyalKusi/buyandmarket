<x-mail::message>
# Changes needed on your listing

Hi {{ $product->store->seller->user->name }},

We reviewed **{{ $product->title }}** and it needs some changes before it can go live.

**Reason:** {{ $reasonCode }}
@if ($note)

{{ $note }}
@endif

Your listing has been moved back to drafts — make the changes and submit it for review again.

<x-mail::button :url="route('seller.dashboard.products')">
Edit your listing
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
