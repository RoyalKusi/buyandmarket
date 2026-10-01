<?php

namespace App\Livewire\Storefront;

use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Design System §6.6: "line items grouped by seller ... the primary UI
 * signal that one order may split into multiple shipments." Mounted once
 * in the storefront layout's header; #[On('cart-updated')] is how
 * AddToCartForm (PDP) and this drawer's own quantity/remove actions stay
 * in sync without a full page reload.
 */
class CartDrawer extends Component
{
    public bool $open = false;

    #[On('cart-updated')]
    public function refresh(): void
    {
        // Livewire re-renders on any dispatched event this component
        // listens for — nothing to do here beyond letting that happen;
        // the method exists so the event has a listener to bind to.
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function updateQuantity(int $itemId, int $quantity): void
    {
        $item = $this->ownedItem($itemId);

        app(CartService::class)->updateQuantity($item, $quantity);

        $this->dispatch('cart-updated');
    }

    public function removeItem(int $itemId): void
    {
        app(CartService::class)->removeItem($this->ownedItem($itemId));

        $this->dispatch('cart-updated');
    }

    private function ownedItem(int $itemId): CartItem
    {
        $item = CartItem::with('cart')->findOrFail($itemId);

        $owns = Auth::check()
            ? $item->cart->user_id === Auth::id()
            : $item->cart->session_id === session()->getId();

        abort_unless($owns, 404);

        return $item;
    }

    public function render()
    {
        $cart = app(CartService::class)->getOrCreateCart(Auth::user(), session()->getId());
        $cart->load('items');

        return view('livewire.storefront.cart-drawer', [
            'groups' => $cart->itemsGroupedByStore(),
            'itemCount' => $cart->items->sum('quantity'),
            'subtotal' => $cart->subtotal(),
        ]);
    }
}
