<?php

namespace Tests\Feature\Commerce;

use App\Models\Address;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * TDD §14 Run 1.5 exit criterion: "A real payment can be completed
 * end-to-end in staging with a Pesepay sandbox" — this test drives the
 * same state machine against a faked Paynow response (network access to
 * a real sandbox isn't available in this environment; the gateway
 * adapters themselves are unit/contract-tested against both providers).
 */
class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    private function buyVariant(): ProductVariant
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create(['base_price' => '20.00']);

        return ProductVariant::factory()->for($product)->create(['price_override' => null, 'stock_quantity' => 5]);
    }

    public function test_the_full_checkout_state_machine_completes_a_payment_end_to_end(): void
    {
        Http::fake([
            'https://www.paynow.co.zw/interface/initiatetransaction' => Http::response(
                'status=Ok&browserurl=https://www.paynow.co.zw/pay/abc&pollurl=https://www.paynow.co.zw/poll/abc',
                200,
            ),
        ]);

        $buyer = User::factory()->withRole('buyer')->create();
        $address = Address::factory()->for($buyer)->create();
        $variant = $this->buyVariant();

        // 1. Add to cart.
        $this->actingAs($buyer)
            ->postJson('/api/v1/carts/items', ['variant_id' => $variant->id, 'quantity' => 2])
            ->assertCreated();

        // 2. Start checkout (CartReview).
        $sessionId = $this->actingAs($buyer)
            ->postJson('/api/v1/checkout/session')
            ->assertCreated()
            ->assertJsonPath('data.status', 'cart_review')
            ->json('data.id');

        // 3. Address selection.
        $this->actingAs($buyer)
            ->patchJson("/api/v1/checkout/session/{$sessionId}/address", ['address_id' => $address->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'address_selection');

        // 4. Delivery method.
        $storeId = $variant->product->store_id;
        $this->actingAs($buyer)
            ->patchJson("/api/v1/checkout/session/{$sessionId}/delivery", [
                'selection' => [$storeId => ['fee' => '3.00']],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'delivery_method');

        // 5. Initiate payment — this is where the Order + order_groups +
        // order_items + inventory reservation + commission all get
        // created (App\Services\OrderService).
        $response = $this->actingAs($buyer)
            ->postJson("/api/v1/checkout/session/{$sessionId}/payment", ['provider' => 'paynow'])
            ->assertOk();

        $response->assertJsonPath('data.redirect_url', 'https://www.paynow.co.zw/pay/abc');

        $this->assertDatabaseHas('orders', ['user_id' => $buyer->id, 'total' => '43.00']);
        $order = Order::where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame(1, $order->orderGroups()->count());
        $this->assertSame('40.00', (string) $order->orderGroups()->first()->subtotal);
        $this->assertSame('3.00', (string) $order->orderGroups()->first()->delivery_fee);

        // Stock reserved immediately (before payment confirms).
        $this->assertSame(3, $variant->fresh()->stock_quantity);

        $payment = $order->payments()->firstOrFail();
        $this->assertSame('initiated', $payment->status);

        // 6. Provider webhook confirms payment.
        $webhookFields = [
            'reference' => $payment->provider_reference,
            'paynowreference' => 'PN-12345',
            'amount' => '43.00',
            'status' => 'Paid',
            'pollurl' => 'https://www.paynow.co.zw/poll/abc',
        ];
        $webhookFields['hash'] = strtoupper(hash(
            'sha512',
            implode('', $webhookFields).config('services.paynow.integration_key')
        ));

        $this->post('/api/v1/webhooks/paynow', $webhookFields)->assertOk();

        $this->assertSame('succeeded', $payment->fresh()->status);
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame('confirmed', $order->orderGroups()->first()->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'order.confirmed',
            'subject_id' => $order->id,
        ]);
    }

    public function test_a_redelivered_webhook_does_not_reprocess_the_payment(): void
    {
        $order = Order::factory()->create(['total' => '10.00']);
        $payment = Payment::factory()->for($order)->create(['provider' => 'paynow', 'status' => 'initiated']);

        $fields = [
            'reference' => $payment->provider_reference,
            'amount' => '10.00',
            'status' => 'Paid',
        ];
        $fields['hash'] = strtoupper(hash('sha512', implode('', $fields).config('services.paynow.integration_key')));

        $this->post('/api/v1/webhooks/paynow', $fields)->assertOk();
        $this->assertSame('succeeded', $payment->fresh()->status);

        // Redelivered — must not throw, must not change anything further.
        $this->post('/api/v1/webhooks/paynow', $fields)->assertOk();
        $this->assertSame('succeeded', $payment->fresh()->status);
    }

    public function test_a_webhook_with_an_invalid_signature_is_rejected(): void
    {
        $payment = Payment::factory()->create(['provider' => 'paynow']);

        $this->post('/api/v1/webhooks/paynow', [
            'reference' => $payment->provider_reference,
            'status' => 'Paid',
            'hash' => 'not-a-real-hash',
        ])->assertForbidden();

        $this->assertSame('initiated', $payment->fresh()->status);
    }

    public function test_checking_out_an_empty_cart_is_rejected(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();

        $this->actingAs($buyer)
            ->postJson('/api/v1/checkout/session')
            ->assertUnprocessable();
    }

    public function test_a_guest_can_check_out_without_an_account(): void
    {
        Http::fake([
            'https://www.paynow.co.zw/*' => Http::response('status=Ok&browserurl=https://paynow.test/pay', 200),
        ]);

        $variant = $this->buyVariant();

        // Testing::withSession() only seeds the container's session
        // singleton — it never attaches a cookie to outgoing test
        // requests, so two chained postJson() calls would each get a
        // brand-new StartSession-issued session id and never see each
        // other's cart. A real guest browser carries the Set-Cookie from
        // the first response back on the next request, so that's
        // simulated here: withCredentials() makes postJson()/getJson()
        // actually attach cookies at all (they omit them by default,
        // mirroring a cross-origin fetch()), then TestResponse::getCookie()
        // decrypts the Set-Cookie payload down to the plain session id, and
        // withCookie() re-encrypts *that* (once) the same way a fresh
        // outgoing request cookie normally would, so EncryptCookies can
        // decrypt it again on the next simulated request.
        $this->withCredentials();

        $firstResponse = $this->postJson('/api/v1/carts/items', ['variant_id' => $variant->id, 'quantity' => 1])
            ->assertCreated();

        $sessionCookieName = config('session.cookie');
        $sessionCookie = $firstResponse->getCookie($sessionCookieName);
        $this->assertNotNull($sessionCookie, 'Expected the guest-session middleware to issue a session cookie.');
        $this->withCookie($sessionCookieName, $sessionCookie->getValue());

        $this->postJson('/api/v1/checkout/session', ['guest_email' => 'guest@example.com'])
            ->assertCreated()
            ->assertJsonPath('data.guest_email', 'guest@example.com');
    }
}
