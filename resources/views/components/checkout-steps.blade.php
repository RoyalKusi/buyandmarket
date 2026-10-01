@props(['current'])
@php
    $steps = ['address' => '1. Address', 'delivery' => '2. Delivery', 'payment' => '3. Payment & review'];
    $order = array_keys($steps);
    $currentIndex = array_search($current, $order, true);
@endphp
<nav aria-label="Checkout progress" class="flex items-center gap-3 text-body-sm">
    @foreach ($steps as $key => $label)
        <span class="{{ array_search($key, $order, true) <= $currentIndex ? 'text-blue-600 font-medium' : 'text-slate-400' }}">
            {{ $label }}
        </span>
        @if (! $loop->last)
            <span class="text-slate-300">&rarr;</span>
        @endif
    @endforeach
</nav>
