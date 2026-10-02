<?php

namespace Tests\Feature\Commerce;

use App\Models\Address;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Second independent sweep finding (P1, reliability): a network-level
 * failure reaching Pesepay/Paynow (timeout, DNS, connection refused —
 * routine for any external API, not an edge case) threw an uncaught
 * ConnectionException straight through CheckoutController, producing a
 * raw 500 instead of TDD §6.6's own "payment failure recovery:
 * dedicated state, not a silent crash." Fixed in both gateways by
 * catching ConnectionException and reusing the exact same graceful-
 * decline path already used when a gateway responds but rejects the
 * payment.
 */
class PaymentGatewayConnectionFailureTest extends TestCase
{
    use RefreshDatabase;

    private function checkoutReadyForPayment(): \App\Models\CheckoutSession
    {
        $buyer = User::factory()->withRole('buyer')->create();
        $address = Address::factory()->for($buyer)->create();
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create(['base_price' => '20.00']);
        $variant = ProductVariant::factory()->for($product)->create(['price_override' => null, 'stock_quantity' => 5]);

        $cart = app(CartService::class)->getOrCreateCart($buyer, null);
        app(CartService::class)->addItem($cart, $variant, 1);

        $checkoutService = app(CheckoutService::class);
        $session = $checkoutService->start($cart, $buyer);
        $checkoutService->setAddress($session, $address);
        $checkoutService->setDeliveryMethod($session, [$seller->id => ['zone_id' => null, 'method' => 'pickup', 'fee' => 0]]);

        $this->actingAs($buyer);

        return $session;
    }

    public function test_pesepay_connection_failure_lands_on_the_pending_page_with_a_friendly_message_not_a_crash(): void
    {
        Http::fake([
            'https://api.pesepay.com/*' => fn () => throw new ConnectionException('Connection timed out'),
        ]);

        $session = $this->checkoutReadyForPayment();

        $response = $this->post(route('storefront.checkout.payment.store', $session), ['provider' => 'pesepay']);

        $response->assertOk(); // the pending/failure view, not a 500
        $response->assertSeeText('try again');
        $this->assertDatabaseHas('payments', ['order_id' => $session->fresh()->order_id, 'status' => 'failed']);
    }

    public function test_paynow_connection_failure_lands_on_the_pending_page_with_a_friendly_message_not_a_crash(): void
    {
        Http::fake([
            'https://www.paynow.co.zw/*' => fn () => throw new ConnectionException('Connection timed out'),
        ]);

        $session = $this->checkoutReadyForPayment();

        $response = $this->post(route('storefront.checkout.payment.store', $session), ['provider' => 'paynow']);

        $response->assertOk();
        $response->assertSeeText('try again');
        $this->assertDatabaseHas('payments', ['order_id' => $session->fresh()->order_id, 'status' => 'failed']);
    }
}
