<?php

namespace App\Contracts;

use App\Models\Payment;

/**
 * What a PaymentGateway::initiate() call hands back to the checkout flow:
 * the payments row it created, plus where to send the buyer to complete
 * payment (a hosted redirect URL for Pesepay/Paynow web, or null when the
 * provider's flow is entirely in-band, e.g. a USSD/mobile-money push).
 */
final readonly class PaymentInitiationResult
{
    public function __construct(
        public Payment $payment,
        public ?string $redirectUrl,
        public ?string $instructions = null,
    ) {}
}
