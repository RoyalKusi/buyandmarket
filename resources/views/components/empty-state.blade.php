{{--
    Design System §5.4: "custom two-colour illustration + heading-md
    message + one clear primary action — never a bare 'No results' with
    no next step." The illustration itself (§2.4: flat geometric, two-
    colour blue-600 + gold-500, built from the bar/stripe vocabulary) is a
    designed asset this run doesn't ship — the bar motif below is a
    minimal placeholder built from the same tokens, not a stand-in for
    real illustration work.
--}}
@props(['heading', 'message' => null, 'actionLabel' => null, 'actionHref' => null])

<div class="flex flex-col items-center text-center py-16 px-6">
    <div class="flex items-end gap-1 h-12 mb-6" aria-hidden="true">
        <span class="w-2 bg-blue-200 rounded-full" style="height: 40%"></span>
        <span class="w-2 bg-blue-400 rounded-full" style="height: 70%"></span>
        <span class="w-2 bg-gold-500 rounded-full" style="height: 100%"></span>
    </div>

    <h2 class="text-heading-md text-slate-900">{{ $heading }}</h2>

    @if ($message)
        <p class="text-body-md text-slate-600 mt-2 max-w-sm">{{ $message }}</p>
    @endif

    @if ($actionLabel && $actionHref)
        <a
            href="{{ $actionHref }}"
            class="mt-6 inline-flex items-center justify-center h-12 px-6 rounded-sm bg-blue-600 text-slate-0 text-button hover:bg-blue-500 transition-colors duration-fast"
        >
            {{ $actionLabel }}
        </a>
    @endif

    {{ $slot }}
</div>
