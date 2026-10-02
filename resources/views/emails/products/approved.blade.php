<x-mail::message>
# Your listing is live

Hi {{ $product->store->seller->user->name }},

**{{ $product->title }}** passed review and is now published — buyers can find and purchase it right away.

<x-mail::button :url="route('seller.dashboard.products')">
View your products
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
