<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\CheckoutSession;
use App\Models\Order;
use App\Models\Payment;
use App\Services\CheckoutService;
use App\Services\NotificationMailer;
use App\Services\OrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Shared plumbing between every PaymentGateway implementation: recording
 * the initial payments row, and idempotently applying a webhook-driven
 * status transition (TDD §7.4's "a redelivered webhook is acknowledged
 * but not reprocessed", keyed on UNIQUE(provider, provider_reference)).
 */
abstract class AbstractPaymentGateway implements PaymentGateway
{
    abstract protected function providerName(): string;

    public function __construct(
        protected readonly OrderService $orderService,
        protected readonly CheckoutService $checkoutService,
        protected readonly NotificationMailer $notificationMailer,
    ) {}

    /**
     * TDD §7.4 `return_url`: falls back to this app's own checkout-return
     * route (App\Http\Controllers\Storefront\CheckoutController::return())
     * when unconfigured — computed at call time (inside initiate(), always
     * within a real HTTP request) rather than in config/services.php,
     * where url() has no bound Request to resolve the host from in every
     * context (e.g. artisan commands).
     */
    protected function defaultReturnUrl(): string
    {
        return url('/checkout/return');
    }

    protected function recordInitiatedPayment(Order $order, string $providerReference): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'provider' => $this->providerName(),
            'provider_reference' => $providerReference,
            'amount' => $order->total,
            'status' => 'initiated',
        ]);
    }

    /**
     * Found while testing the notification module: a gateway that
     * declines synchronously (responds immediately with a rejection, or
     * fails to connect at all — initiate()'s own catch blocks) never
     * reaches applyWebhookResult()'s 'failed' branch, so
     * CheckoutService::markPaymentFailed()'s email there never fires for
     * this path. This is the equivalent for the synchronous case, kept
     * here rather than duplicated in each gateway.
     */
    protected function markInitiationFailed(Payment $payment): void
    {
        $this->notificationMailer->paymentFailed($payment->order);
    }

    /**
     * @param  'processing'|'succeeded'|'failed'  $status
     */
    protected function applyWebhookResult(string $providerReference, string $status, array $rawPayload): void
    {
        DB::transaction(function () use ($providerReference, $status, $rawPayload) {
            $payment = Payment::where('provider', $this->providerName())
                ->where('provider_reference', $providerReference)
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                Log::warning('Webhook received for unknown payment reference', [
                    'provider' => $this->providerName(),
                    'reference' => $providerReference,
                ]);

                return;
            }

            // Idempotency: a payment already in a terminal state (succeeded
            // or failed) never transitions again from a redelivered webhook.
            if (in_array($payment->status, ['succeeded', 'failed'], true)) {
                return;
            }

            $payment->update(['status' => $status, 'raw_payload' => $rawPayload]);

            // Run 1.12 fix: CheckoutService::confirmOrder()/markPaymentFailed()
            // existed since Run 1.5 but nothing ever called them — a
            // checkout_sessions row stayed stuck at 'payment_processing'
            // forever, even after its order was confirmed. The storefront
            // checkout UI's return/failed pages depend on this being correct.
            $session = CheckoutSession::where('order_id', $payment->order_id)->first();

            if ($status === 'succeeded') {
                $this->orderService->confirmPaidOrder($payment->order);

                if ($session !== null) {
                    $this->checkoutService->confirmOrder($session);
                }
            } elseif ($status === 'failed' && $session !== null) {
                $this->checkoutService->markPaymentFailed($session);
            }
        });
    }
}
