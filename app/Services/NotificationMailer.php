<?php

namespace App\Services;

use App\Mail\OrderConfirmed;
use App\Mail\PaymentFailed;
use App\Mail\ProductApproved;
use App\Mail\ProductRejected;
use App\Mail\SellerKycApproved;
use App\Mail\SellerKycRejected;
use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Production Readiness Report condition #1: "Wire up transactional
 * notifications — at minimum order confirmation and payment-failure
 * emails." This is that module — every outbound transactional email in
 * the app goes through here, never through a direct `Mail::send()` call
 * elsewhere, so the recipient-resolution and failure-handling rules
 * below apply uniformly.
 *
 * Two rules every method here follows:
 *
 * 1. Never let a mail failure break the business transaction that
 *    triggered it. A broken SMTP credential must never roll back an
 *    admin's KYC approval or silently fail an order confirmation
 *    webhook — every send is wrapped and logged, never thrown. Call
 *    sites invoke these methods AFTER their own DB::transaction()
 *    commits, for the same reason (the email is a side effect of a
 *    state change that already durably happened, not part of it).
 * 2. Not queued (ShouldQueue). This launch topology has no queue
 *    worker guaranteed running (TDD §2.1, the same reasoning already
 *    documented for the image pipeline and ai:reindex) — a queued mail
 *    would simply sit in the `jobs` table forever rather than degrade
 *    gracefully. Sent synchronously instead; MAIL_MAILER defaults to
 *    `log` in every environment until a real transport is configured
 *    (.env.example), so nothing here depends on an external service to
 *    boot or to pass tests.
 */
class NotificationMailer
{
    public function orderConfirmed(Order $order): void
    {
        $this->send($this->recipientFor($order), new OrderConfirmed($order));
    }

    public function paymentFailed(Order $order): void
    {
        $this->send($this->recipientFor($order), new PaymentFailed($order));
    }

    public function sellerKycApproved(Seller $seller): void
    {
        $this->send($seller->user->email, new SellerKycApproved($seller));
    }

    public function sellerKycRejected(Seller $seller, string $reasonCode, ?string $note): void
    {
        $this->send($seller->user->email, new SellerKycRejected($seller, $reasonCode, $note));
    }

    public function productApproved(Product $product): void
    {
        $this->send($product->store->seller->user->email, new ProductApproved($product));
    }

    public function productRejected(Product $product, string $reasonCode, ?string $note): void
    {
        $this->send($product->store->seller->user->email, new ProductRejected($product, $reasonCode, $note));
    }

    private function recipientFor(Order $order): ?string
    {
        return $order->user?->email ?? $order->guest_email;
    }

    private function send(?string $recipient, Mailable $mailable): void
    {
        if ($recipient === null || $recipient === '') {
            // A guest order with a phone number but no email (TDD §6.2:
            // "phone or email required, not both") has nowhere to send
            // this — not an error, just nothing to do.
            return;
        }

        try {
            Mail::to($recipient)->send($mailable);
        } catch (Throwable $e) {
            Log::error('Transactional email failed to send', [
                'mailable' => $mailable::class,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
