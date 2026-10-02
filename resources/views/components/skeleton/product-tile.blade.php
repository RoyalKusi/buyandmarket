{{--
    Design System §5.3: matches the real tile's exact box model so nothing
    reflows when data arrives. The shimmer itself needs a keyframe defined
    once globally (resources/css/app.css) — this markup only applies it.
--}}
<div class="bg-slate-0 border border-slate-100 rounded-md overflow-hidden flex flex-col" aria-hidden="true">
    <div class="aspect-square bg-slate-100 skeleton-shimmer"></div>
    <div class="px-5 pt-4 pb-4 flex flex-col gap-2">
        <div class="h-4 w-full bg-slate-100 rounded-xs skeleton-shimmer"></div>
        <div class="h-4 w-4/5 bg-slate-100 rounded-xs skeleton-shimmer"></div>
        <div class="h-4 w-3/5 bg-slate-100 rounded-xs skeleton-shimmer"></div>
    </div>
</div>
