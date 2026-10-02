<?php

namespace Tests\Feature\Commerce;

use App\Models\Address;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockRevalidationAtOrderCreationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Audit finding (P1): OrderService::createFromCheckoutSession never
     * re-checked stock at order-creation time, only at checkout-session
     * start — minutes earlier. Fixed by re-fetching each variant with
     * lockForUpdate() and re-validating stock inside the same
     * transaction (app/Services/OrderService.php). This now proves the
     * fix: a ValidationException is thrown and stock never goes
     * negative, instead of silently oversold inventory / a 500 from
     * MySQL's strict-mode unsigned-column rejection.
     */
    public function test_stock_is_revalidated_at_order_creation_time(): void
    {
        Http::fake([
            'https://www.paynow.co.zw/interface/initiatetransaction' => Http::response(
                'status=Ok&browserurl=https://www.paynow.co.zw/pay/abc&pollurl=https://www.paynow.co.zw/poll/abc',
                200,
            ),
        ]);

        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 1]);

        $cart = Cart::factory()->create();
        $cart->items()->create(['variant_id' => $variant->id, 'quantity' => 1, 'price_snapshot' => 10]);

        $checkoutService = app(CheckoutService::class);
        $session = $checkoutService->start($cart, null, 'buyer@example.com', '0771234567');

        // Simulate stock disappearing between checkout start and payment
        // initiation (another buyer bought it, or an admin adjusted it) —
        // no lock is held across this window, and no re-check happens.
        $variant->update(['stock_quantity' => 0]);

        $checkoutService->setAddress($session, Address::factory()->create(['user_id' => null]));
        $checkoutService->setDeliveryMethod($session, [$seller->id => ['zone_id' => null, 'method' => 'pickup', 'fee' => 0]]);

        $this->expectException(ValidationException::class);

        try {
            $checkoutService->initiatePayment($session, 'paynow');
        } finally {
            $finalStock = $variant->fresh()->stock_quantity;
            $this->assertGreaterThanOrEqual(0, $finalStock, "Stock went negative ({$finalStock}) — oversold with no re-validation.");
        }
    }
}
