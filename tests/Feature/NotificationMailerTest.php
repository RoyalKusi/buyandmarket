<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmed;
use App\Mail\PaymentFailed;
use App\Mail\ProductApproved;
use App\Mail\ProductRejected;
use App\Mail\SellerKycApproved;
use App\Mail\SellerKycRejected;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CheckoutSession;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\KycReviewService;
use App\Services\NotificationMailer;
use App\Services\OrderService;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Production Readiness Report condition #1: "Wire up transactional
 * notifications — at minimum order confirmation and payment-failure
 * emails." App\Services\NotificationMailer is the in-code module every
 * one of these routes through; this is its regression suite, covering
 * both the module itself (recipient resolution, failure isolation) and
 * every real call site that triggers it.
 */
class NotificationMailerTest extends TestCase
{
    use RefreshDatabase;

    private function checkoutReadyForPayment(?User $buyer = null): CheckoutSession
    {
        $buyer = $buyer ?? User::factory()->withRole('buyer')->create();
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

        return $session;
    }

    public function test_confirming_a_paid_order_emails_the_buyer(): void
    {
        Mail::fake();

        $buyer = User::factory()->withRole('buyer')->create();
        $order = Order::factory()->for($buyer)->create(['status' => 'pending']);

        app(OrderService::class)->confirmPaidOrder($order);

        Mail::assertSent(OrderConfirmed::class, fn ($mail) => $mail->hasTo($buyer->email) && $mail->order->is($order));
    }

    public function test_a_guest_order_confirmation_goes_to_the_guest_email(): void
    {
        Mail::fake();

        $order = Order::factory()->create(['user_id' => null, 'guest_email' => 'guest@example.com', 'status' => 'pending']);

        app(OrderService::class)->confirmPaidOrder($order);

        Mail::assertSent(OrderConfirmed::class, fn ($mail) => $mail->hasTo('guest@example.com'));
    }

    public function test_an_already_confirmed_order_does_not_resend_on_a_redelivered_webhook(): void
    {
        Mail::fake();

        $buyer = User::factory()->withRole('buyer')->create();
        $order = Order::factory()->for($buyer)->create(['status' => 'confirmed']);

        app(OrderService::class)->confirmPaidOrder($order);

        Mail::assertNothingSent();
    }

    public function test_a_failed_payment_emails_the_buyer_with_a_graceful_message(): void
    {
        Mail::fake();
        Http::fake([
            'https://www.paynow.co.zw/*' => Http::response('status=Error&error=Declined', 200),
        ]);

        $buyer = User::factory()->withRole('buyer')->create();
        $session = $this->checkoutReadyForPayment($buyer);

        $this->actingAs($buyer)->post(route('storefront.checkout.payment.store', $session), ['provider' => 'paynow']);

        Mail::assertSent(PaymentFailed::class, fn ($mail) => $mail->hasTo($buyer->email));
    }

    public function test_a_webhook_reported_payment_failure_emails_the_buyer(): void
    {
        Mail::fake();

        $buyer = User::factory()->withRole('buyer')->create();
        $order = Order::factory()->for($buyer)->create(['total' => '10.00']);
        $payment = Payment::factory()->for($order)->create(['provider' => 'paynow', 'status' => 'initiated']);
        CheckoutSession::create([
            'cart_id' => Cart::factory()->create()->id,
            'user_id' => $buyer->id,
            'status' => 'payment_processing',
            'order_id' => $order->id,
            'expires_at' => now()->addMinutes(30),
        ]);

        $fields = [
            'reference' => $payment->provider_reference,
            'amount' => '10.00',
            'status' => 'Cancelled',
        ];
        $fields['hash'] = strtoupper(hash('sha512', implode('', $fields).config('services.paynow.integration_key')));

        $this->post('/api/v1/webhooks/paynow', $fields)->assertOk();

        Mail::assertSent(PaymentFailed::class, fn ($mail) => $mail->hasTo($buyer->email));
    }

    public function test_an_approved_kyc_review_emails_the_seller(): void
    {
        Mail::fake();

        $seller = Seller::factory()->create(['status' => 'under_review', 'kyc_status' => 'pending']);
        $admin = User::factory()->withRole('admin')->create();

        app(KycReviewService::class)->approve($seller, $admin);

        Mail::assertSent(SellerKycApproved::class, fn ($mail) => $mail->hasTo($seller->user->email));
    }

    public function test_a_rejected_kyc_review_emails_the_seller_with_the_reason(): void
    {
        Mail::fake();

        $seller = Seller::factory()->create(['status' => 'under_review', 'kyc_status' => 'pending']);
        $admin = User::factory()->withRole('admin')->create();

        app(KycReviewService::class)->reject($seller, $admin, 'blurry_documents', 'Please re-upload a clearer photo.');

        Mail::assertSent(SellerKycRejected::class, fn ($mail) => $mail->hasTo($seller->user->email)
            && $mail->reasonCode === 'blurry_documents'
            && $mail->note === 'Please re-upload a clearer photo.');
    }

    public function test_an_approved_product_emails_the_seller(): void
    {
        Mail::fake();

        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->create(['status' => 'pending_review']);
        $admin = User::factory()->withRole('admin')->create();

        app(ProductService::class)->approve($product, $admin);

        Mail::assertSent(ProductApproved::class, fn ($mail) => $mail->hasTo($seller->user->email));
    }

    public function test_a_rejected_product_emails_the_seller_with_the_reason(): void
    {
        Mail::fake();

        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->create(['status' => 'pending_review']);
        $admin = User::factory()->withRole('admin')->create();

        app(ProductService::class)->reject($product, $admin, 'misleading_title', null);

        Mail::assertSent(ProductRejected::class, fn ($mail) => $mail->hasTo($seller->user->email)
            && $mail->reasonCode === 'misleading_title');
    }

    public function test_a_mail_failure_is_logged_but_never_breaks_the_underlying_business_action(): void
    {
        // Simulates a broken SMTP configuration: the admin's KYC approval
        // must still succeed and commit even if the resulting email
        // can't be sent.
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('Connection refused'));
        Log::shouldReceive('error')->once();

        $seller = Seller::factory()->create(['status' => 'under_review', 'kyc_status' => 'pending']);
        $admin = User::factory()->withRole('admin')->create();

        app(KycReviewService::class)->approve($seller, $admin);

        $this->assertSame('active', $seller->fresh()->status);
        $this->assertSame('approved', $seller->fresh()->kyc_status);
    }

    public function test_a_guest_order_with_no_email_on_file_is_silently_skipped_not_errored(): void
    {
        Mail::fake();

        $order = Order::factory()->create(['user_id' => null, 'guest_email' => null, 'guest_phone' => '0771234567', 'status' => 'pending']);

        app(NotificationMailer::class)->orderConfirmed($order);

        Mail::assertNothingSent();
    }

    /**
     * Mail::fake() (used throughout this file) intercepts dispatch
     * before rendering, so it never actually compiles the Markdown
     * Blade templates — a typo in one would pass every test above and
     * still break in production. This renders every template for real
     * against representative data, the only way to catch that.
     */
    public function test_every_transactional_email_template_renders_without_error(): void
    {
        $buyer = User::factory()->withRole('buyer')->create(['name' => 'Tendai Buyer']);
        $order = Order::factory()->for($buyer)->create(['order_number' => 'BM-20261002-TEST01', 'total' => '42.50']);
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->create(['title' => 'Bluetooth Speaker']);

        $orderGroup = OrderGroup::factory()->for($order)->for($seller)->create();
        $variant = ProductVariant::factory()->for($product)->create(['sku' => 'BT-SPK-01']);
        OrderItem::factory()->for($orderGroup)->for($variant, 'variant')->create(['quantity' => 2, 'price_at_purchase' => '19.99']);
        $order->load('orderGroups.items.variant.product', 'user');

        $rendered = [
            (new OrderConfirmed($order))->render(),
            (new PaymentFailed($order))->render(),
            (new SellerKycApproved($seller))->render(),
            (new SellerKycRejected($seller, 'blurry_documents', 'Please re-upload a clearer photo.'))->render(),
            (new SellerKycRejected($seller, 'blurry_documents', null))->render(),
            (new ProductApproved($product))->render(),
            (new ProductRejected($product, 'misleading_title', null))->render(),
        ];

        foreach ($rendered as $html) {
            $this->assertStringContainsString('<!DOCTYPE html', $html);
            $this->assertStringNotContainsString('Facade root has not been set', $html);
        }

        $this->assertStringContainsString('BM-20261002-TEST01', $rendered[0]);
        $this->assertStringContainsString('Bluetooth Speaker', $rendered[0]);
        $this->assertStringContainsString('42.50', $rendered[0]);
    }
}
