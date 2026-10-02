<?php

namespace App\Livewire\Storefront;

use App\Models\Product;
use App\Services\WishlistService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * PDP heart-icon toggle — only rendered for a signed-in buyer (a guest
 * sees nothing here, same as the cart's own guest/auth split elsewhere
 * in this build keeps wishlisting tied to an account, not a session).
 */
class WishlistButton extends Component
{
    public Product $product;

    public bool $wishlisted = false;

    public function mount(Product $product, WishlistService $wishlistService): void
    {
        $this->product = $product;
        $this->wishlisted = Auth::check() && $wishlistService->contains(Auth::user(), $product);
    }

    public function toggle(WishlistService $wishlistService): void
    {
        $this->wishlisted = $wishlistService->toggle(Auth::user(), $this->product);
    }

    public function render()
    {
        return view('livewire.storefront.wishlist-button');
    }
}
