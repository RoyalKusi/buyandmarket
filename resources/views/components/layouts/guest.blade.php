<?php /** @var string $title */ ?>
@props(['title' => 'BuyAndMarket'])
<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50 flex items-center justify-center px-4">
    <div class="w-full max-w-[420px] py-16">
        <a href="{{ route('storefront.home') }}" class="flex justify-center mb-8" aria-label="BuyAndMarket home">
            <img src="{{ asset('images/logo-blue.png') }}" alt="BuyAndMarket" class="h-11 w-auto">
        </a>

        <div class="bg-slate-0 border border-slate-100 rounded-md shadow-[0_1px_2px_rgba(19,25,38,.06),0_1px_1px_rgba(19,25,38,.04)] p-8">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
