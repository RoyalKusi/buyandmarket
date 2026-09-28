<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\Payment;
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

    public function __construct(protected readonly OrderService $orderService) {}

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

            if ($status === 'succeeded') {
                $this->orderService->confirmPaidOrder($payment->order);
            }
        });
    }
}
