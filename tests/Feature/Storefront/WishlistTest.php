<?php

namespace Tests\Feature\Storefront;

use App\Livewire\Storefront\WishlistButton;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TDD §3.5-adjacent buyer feature, flagged deferred since Run 1.4
 * ("cart and wishlist have no backing module until Run 1.5/§3.5" — cart
 * shipped in Run 1.5, wishlist in Run 1.18).
 */
class WishlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_buyer_can_toggle_a_product_on_and_off_their_wishlist(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create();

        Livewire::actingAs($buyer)
            ->test(WishlistButton::class, ['product' => $product])
            ->assertSet('wishlisted', false)
            ->call('toggle')
            ->assertSet('wishlisted', true);

        $this->assertDatabaseHas('wishlist_items', ['user_id' => $buyer->id, 'product_id' => $product->id]);

        Livewire::actingAs($buyer)
            ->test(WishlistButton::class, ['product' => $product])
            ->assertSet('wishlisted', true)
            ->call('toggle')
            ->assertSet('wishlisted', false);

        $this->assertDatabaseMissing('wishlist_items', ['user_id' => $buyer->id, 'product_id' => $product->id]);
    }

    public function test_a_buyer_can_view_and_remove_items_from_their_wishlist_dashboard_page(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create(['title' => 'Bluetooth Speaker']);

        Livewire::actingAs($buyer)
            ->test(WishlistButton::class, ['product' => $product])
            ->call('toggle');

        $this->actingAs($buyer)->get('/dashboard/wishlist')->assertOk()->assertSeeText('Bluetooth Speaker');

        $this->actingAs($buyer)
            ->delete(route('dashboard.wishlist.destroy', $product))
            ->assertRedirect();

        $this->assertDatabaseMissing('wishlist_items', ['user_id' => $buyer->id, 'product_id' => $product->id]);
    }

    public function test_a_guest_sees_no_wishlist_button_but_the_pdp_still_renders(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create();

        $this->get(route('storefront.products.show', $product))
            ->assertOk()
            ->assertSee('Sign in to save to your wishlist');
    }
}
