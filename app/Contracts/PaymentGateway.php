<?php

namespace App\Contracts;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * TDD §7.4: "initiate(), verifyWebhookSignature(), handleWebhook(),
 * refund(), getStatus() — the Pesepay adapter is the first
 * implementation; a second provider is a second class, no call-site
 * changes elsewhere in the codebase." Paynow is that second
 * implementation, added in this same run.
 */
interface PaymentGateway
{
    /**
     * Start a payment with the provider and record the initial `payments`
     * row (status `initiated`). Returns the URL/instructions the buyer is
     * sent to.
     */
    public function initiate(Order $order): PaymentInitiationResult;

    /**
     * Verify an inbound webhook request actually came from this provider
     * (HMAC or provider-specific signature check, TDD §8.3) before any of
     * its payload is trusted.
     */
    public function verifyWebhookSignature(Request $request): bool;

    /**
     * Process an already-signature-verified webhook payload, transitioning
     * the matching `payments` row. Idempotent on (provider,
     * provider_reference) — a redelivered webhook is acknowledged, not
     * reprocessed (TDD §7.4).
     */
    public function handleWebhook(Request $request): void;

    public function refund(Payment $payment, string $amount): void;

    public function getStatus(Payment $payment): string;
}
