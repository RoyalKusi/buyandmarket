<button type="button" wire:click="toggle" class="inline-flex items-center gap-2 text-body-sm {{ $wishlisted ? 'text-red-600' : 'text-slate-600' }} hover:text-red-600">
    <span aria-hidden="true">{{ $wishlisted ? '♥' : '♡' }}</span>
    <span>{{ $wishlisted ? 'Saved to wishlist' : 'Save to wishlist' }}</span>
</button>
