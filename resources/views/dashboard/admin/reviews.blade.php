<x-layouts.dashboard title="Reviews" active="admin.reviews">
    {{-- TDD module 33: a worklist of 1-2 star reviews, where an abuse or
         policy-violation report is most likely to land — not every
         review ever written. --}}
    @if ($reviews->isEmpty())
        <p class="text-body-lg text-slate-500">No low-rated reviews to check right now.</p>
    @else
        <div class="space-y-4">
            @foreach ($reviews as $review)
                <div class="bg-slate-0 border border-slate-100 rounded-md p-6">
                    <div class="flex items-start justify-between mb-2">
                        <div>
                            <p class="text-body-md font-semibold text-slate-900">{{ $review->product->title }}</p>
                            <p class="text-body-sm text-slate-500">{{ $review->user->name }} &middot; {{ $review->rating }}/5 &middot; {{ $review->created_at->format('d M Y') }}</p>
                        </div>
                    </div>
                    @if ($review->title)
                        <p class="text-body-md font-semibold text-slate-900">{{ $review->title }}</p>
                    @endif
                    <p class="text-body-md text-slate-700 mt-1">{{ $review->body }}</p>

                    <form method="POST" action="{{ route('admin.dashboard.reviews.remove', $review) }}" class="mt-4 flex flex-wrap items-end gap-2">
                        @csrf
                        <div>
                            <label class="block text-body-sm text-slate-700 mb-1">Reason code</label>
                            <input type="text" name="reason_code" placeholder="e.g. spam, abusive" required class="h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                        </div>
                        <button type="submit" class="h-10 rounded-sm bg-red-600 text-slate-0 px-4 text-button font-semibold hover:bg-red-700">Remove review</button>
                    </form>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $reviews->links() }}</div>
    @endif
</x-layouts.dashboard>
