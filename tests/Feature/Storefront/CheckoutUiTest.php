<?php

namespace Tests\Feature\Storefront;

use App\Livewire\Storefront\AddToCartForm;
use App\Livewire\Storefront\CartDrawer;
use App\Models\Address;
use App\Models\CheckoutSession;
use App\Models\DeliveryRateCard;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Run 1.12 (deferred-item polish): the storefront never had a working
 * cart drawer, checkout page or order confirmation — PDP's Add to
 * cart/Buy now rendered disabled since Run 1.4 (flagged in CHANGELOG.md
 * across Runs 1.4/1.5). This is the first end-to-end test of a buyer
 * actually completing a purchase through the website rather than the API.
 */
class CheckoutUiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{seller: Seller, product: Product, variant: ProductVariant, zone: DeliveryZone, rateCard: DeliveryRateCard}
     */
    private function sellerWithDeliverableProduct(): array
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create(['base_price' => '20.00']);
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 5]);
        $zone = DeliveryZone::factory()->create();
        $rateCard = DeliveryRateCard::factory()->for($seller)->for($zone, 'zone')->create([
            'method' => 'standard',
            'base_fee' => '4.50',
        ]);

        return compact('seller', 'product', 'variant', 'zone', 'rateCard');
    }

    /**
     * Plain get()/post() test calls don't carry cookies between separate
     * calls by default (each gets a fresh session unless one is
     * explicitly forwarded) — same lesson as the guest-session cart/
     * checkout API tests in Run 1.5's CheckoutFlowTest. This establishes
     * one session up front and seeds the guest's cart directly against
     * it (CartService, not the Livewire UI layer — that path is covered
     * separately by test_adding_to_cart_from_the_pdp_updates_the_cart_drawer).
     */
    private function startGuestSessionWithCart(ProductVariant $variant, int $quantity = 1): void
    {
        $first = $this->get('/');
        $sessionCookieName = config('session.cookie');
        $sessionCookie = $first->getCookie($sessionCookieName);
        $this->withCookie($sessionCookieName, $sessionCookie->getValue());

        $cart = app(CartService::class)->getOrCreateCart(null, $sessionCookie->getValue());
        app(CartService::class)->addItem($cart, $variant, $quantity);
    }

    public function test_adding_to_cart_from_the_pdp_updates_the_cart_drawer(): void
    {
        ['variant' => $variant, 'product' => $product] = $this->sellerWithDeliverableProduct();

        Livewire::test(AddToCartForm::class, ['product' => $product])
            ->set('variantId', $variant->id)
            ->set('quantity', 2)
            ->call('addToCart')
            ->assertSet('status', 'Added to cart.');

        Livewire::test(CartDrawer::class)
            ->set('open', true)
            ->assertSee($product->title)
            ->assertSee('2'); // the quantity input's value
    }

    public function test_a_guest_can_complete_checkout_through_the_website_to_confirmation(): void
    {
        Http::fake([
            'https://www.paynow.co.zw/interface/initiatetransaction' => Http::response(
                'status=Ok&browserurl=https://www.paynow.co.zw/pay/abc',
                200,
            ),
        ]);

        ['variant' => $variant, 'rateCard' => $rateCard] = $this->sellerWithDeliverableProduct();
        $this->startGuestSessionWithCart($variant);

        $this->get('/checkout/start')->assertRedirect();
        $checkoutSession = CheckoutSession::firstOrFail();

        $this->get("/checkout/{$checkoutSession->id}/address")->assertOk()->assertSee('Delivery address');

        $this->post("/checkout/{$checkoutSession->id}/address", [
            'guest_email' => 'guest@example.com',
            'label' => 'Home',
            'recipient_name' => 'Tendai Moyo',
            'phone' => '0771234567',
            'province' => 'Harare',
            'city' => 'Harare',
            'area' => 'Avondale',
            'street_address' => '1 Sample Rd',
        ])->assertRedirect("/checkout/{$checkoutSession->id}/delivery");

        $this->get("/checkout/{$checkoutSession->id}/delivery")->assertOk()->assertSee('4.50');

        $this->post("/checkout/{$checkoutSession->id}/delivery", [
            'selection' => [$variant->product->store_id => $rateCard->id],
        ])->assertRedirect("/checkout/{$checkoutSession->id}/payment");

        $this->get("/checkout/{$checkoutSession->id}/payment")->assertOk()->assertSee('4.50');

        $this->post("/checkout/{$checkoutSession->id}/payment", ['provider' => 'paynow'])
            ->assertRedirect('https://www.paynow.co.zw/pay/abc');

        $order = Order::where('guest_email', 'guest@example.com')->firstOrFail();
        $this->assertSame('24.50', (string) $order->total);
        $this->assertSame('4.50', (string) $order->orderGroups()->first()->delivery_fee);

        // Provider webhook confirms payment — Run 1.12 fix:
        // CheckoutService::confirmOrder() is now actually wired, so the
        // checkout_sessions row (not just the order) reflects it.
        $payment = $order->payments()->firstOrFail();
        $webhookFields = [
            'reference' => $payment->provider_reference,
            'amount' => '24.50',
            'status' => 'Paid',
        ];
        $webhookFields['hash'] = strtoupper(hash('sha512', implode('', $webhookFields).config('services.paynow.integration_key')));
        $this->post('/api/v1/webhooks/paynow', $webhookFields)->assertOk();

        $this->assertSame('order_confirmed', $checkoutSession->fresh()->status);

        $this->get('/checkout/return')->assertRedirect("/checkout/{$checkoutSession->id}/confirmation");
        $this->get("/checkout/{$checkoutSession->id}/confirmation")
            ->assertOk()
            ->assertSee($order->order_number);
    }

    public function test_a_failed_payment_lands_the_buyer_on_the_dedicated_failed_page(): void
    {
        Http::fake([
            'https://www.paynow.co.zw/interface/initiatetransaction' => Http::response(
                'status=Ok&browserurl=https://www.paynow.co.zw/pay/abc',
                200,
            ),
        ]);

        ['variant' => $variant, 'rateCard' => $rateCard] = $this->sellerWithDeliverableProduct();
        $buyer = User::factory()->withRole('buyer')->create();
        $address = Address::factory()->for($buyer)->create();
        app(CartService::class)->addItem(app(CartService::class)->getOrCreateCart($buyer, null), $variant, 1);

        $this->actingAs($buyer)->get('/checkout/start')->assertRedirect();
        $checkoutSession = CheckoutSession::where('user_id', $buyer->id)->firstOrFail();

        $this->actingAs($buyer)->post("/checkout/{$checkoutSession->id}/address", ['address_id' => $address->id]);
        $this->actingAs($buyer)->post("/checkout/{$checkoutSession->id}/delivery", [
            'selection' => [$variant->product->store_id => $rateCard->id],
        ]);
        $this->actingAs($buyer)->post("/checkout/{$checkoutSession->id}/payment", ['provider' => 'paynow']);

        $payment = $checkoutSession->fresh()->order->payments()->firstOrFail();
        $webhookFields = [
            'reference' => $payment->provider_reference,
            'amount' => (string) $payment->amount,
            'status' => 'Cancelled',
        ];
        $webhookFields['hash'] = strtoupper(hash('sha512', implode('', $webhookFields).config('services.paynow.integration_key')));
        $this->post('/api/v1/webhooks/paynow', $webhookFields)->assertOk();

        $this->assertSame('payment_failed', $checkoutSession->fresh()->status);

        $this->actingAs($buyer)->get('/checkout/return')
            ->assertRedirect("/checkout/{$checkoutSession->id}/failed");
        $this->actingAs($buyer)->get("/checkout/{$checkoutSession->id}/failed")
            ->assertOk()
            ->assertSee('Try again');
    }

    public function test_a_tampered_delivery_selection_cannot_borrow_another_sellers_rate_card(): void
    {
        ['variant' => $variant] = $this->sellerWithDeliverableProduct();
        $otherSeller = Seller::factory()->active()->create();
        $otherZone = DeliveryZone::factory()->create();
        $otherRateCard = DeliveryRateCard::factory()->for($otherSeller)->for($otherZone, 'zone')->create(['base_fee' => '0.01']);

        $this->startGuestSessionWithCart($variant);
        $this->get('/checkout/start');
        $checkoutSession = CheckoutSession::firstOrFail();

        $this->post("/checkout/{$checkoutSession->id}/address", [
            'guest_email' => 'guest@example.com',
            'label' => 'Home',
            'recipient_name' => 'Tendai Moyo',
            'phone' => '0771234567',
            'province' => 'Harare',
            'city' => 'Harare',
            'area' => 'Avondale',
            'street_address' => '1 Sample Rd',
        ]);

        $this->post("/checkout/{$checkoutSession->id}/delivery", [
            'selection' => [$variant->product->store_id => $otherRateCard->id],
        ])->assertStatus(404);
    }

    public function test_a_guest_can_create_an_account_from_the_confirmation_page(): void
    {
        Http::fake([
            'https://www.paynow.co.zw/interface/initiatetransaction' => Http::response(
                'status=Ok&browserurl=https://www.paynow.co.zw/pay/abc',
                200,
            ),
        ]);

        ['variant' => $variant, 'rateCard' => $rateCard] = $this->sellerWithDeliverableProduct();
        $this->startGuestSessionWithCart($variant);
        $this->get('/checkout/start');
        $checkoutSession = CheckoutSession::firstOrFail();

        $this->post("/checkout/{$checkoutSession->id}/address", [
            'guest_email' => 'guest@example.com',
            'label' => 'Home',
            'recipient_name' => 'Tendai Moyo',
            'phone' => '0771234567',
            'province' => 'Harare',
            'city' => 'Harare',
            'area' => 'Avondale',
            'street_address' => '1 Sample Rd',
        ]);
        $this->post("/checkout/{$checkoutSession->id}/delivery", [
            'selection' => [$variant->product->store_id => $rateCard->id],
        ]);
        $this->post("/checkout/{$checkoutSession->id}/payment", ['provider' => 'paynow']);

        $order = Order::where('guest_email', 'guest@example.com')->firstOrFail();

        $this->post("/checkout/orders/{$order->id}/upsell", ['password' => 'a-strong-password'])
            ->assertRedirect(route('dashboard.orders.show', $order));

        $this->assertAuthenticated();
        $this->assertSame($order->id, $order->fresh()->id);
        $this->assertNotNull($order->fresh()->user_id);
        $user = User::where('email', 'guest@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('buyer'));
        $this->assertSame($user->id, $order->fresh()->user_id);
    }
}
