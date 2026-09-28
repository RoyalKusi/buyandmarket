{{--
    Design System §4.5: body-sm n-500, "/" separator in n-300, current page
    n-700 medium-weight. Truncation to "Home / … / Current" beyond 3
    levels is a further refinement not yet built — this run's category
    depth (max 3, TDD §6.2) keeps real breadcrumb chains short enough that
    the omission doesn't bite yet.
--}}
@props(['items'])

<nav aria-label="Breadcrumb" class="text-body-sm">
    <ol class="flex items-center flex-wrap gap-1">
        @foreach ($items as $index => $item)
            <li class="flex items-center gap-1">
                @if (! $loop->last)
                    <a href="{{ $item['href'] }}" class="text-slate-500 hover:text-slate-700">{{ $item['label'] }}</a>
                    <span class="text-slate-300" aria-hidden="true">/</span>
                @else
                    <span class="text-slate-700 font-medium" aria-current="page">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
