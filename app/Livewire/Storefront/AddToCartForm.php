<?php

namespace App\Livewire\Storefront;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Design System §6.4/§6.6: PDP "Add to cart" / "Buy now" — both render
 * disabled on the PDP until Run 1.5's cart module existed (TDD §3.4
 * module 17). Checkout (not this component) owns the §6.4 rule 1 price
 * re-validation pass; CartService::addItem() already re-checks product
 * status here.
 */
class AddToCartForm extends Component
{
    public Product $product;

    public ?int $variantId = null;

    public int $quantity = 1;

    public ?string $status = null;

    public function mount(Product $product): void
    {
        $this->product = $product;
        $this->variantId = $product->variants->first()?->id;
    }

    public function addToCart(): void
    {
        $this->status = null;
        $variant = $this->resolveVariant();

        if ($variant === null) {
            $this->addError('variant', 'Select an available option.');

            return;
        }

        $cartService = app(CartService::class);
        $cart = $cartService->getOrCreateCart(Auth::user(), session()->getId());
        $cartService->addItem($cart, $variant, max(1, $this->quantity));

        $this->status = 'Added to cart.';
        $this->dispatch('cart-updated');
    }

    public function buyNow()
    {
        $this->addToCart();

        if ($this->status !== null) {
            return $this->redirect(route('storefront.checkout.start'), navigate: false);
        }
    }

    private function resolveVariant(): ?ProductVariant
    {
        return $this->product->variants
            ->first(fn (ProductVariant $v) => $v->id === $this->variantId && $v->stock_quantity > 0);
    }

    public function render()
    {
        return view('livewire.storefront.add-to-cart-form');
    }
}
