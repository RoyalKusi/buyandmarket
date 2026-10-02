<x-mail::message>
# You're approved!

Hi {{ $seller->user->name }},

Great news — **{{ $seller->business_name }}** has passed review and your seller account is now active. Your store is live and buyers can place orders right away.

<x-mail::button :url="route('seller.dashboard.index')">
Go to your seller dashboard
</x-mail::button>

Thanks for selling with BuyAndMarket.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
