<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use InvalidArgumentException;

/**
 * TDD §7.4 names Pesepay as the first PaymentGateway implementation and
 * says "a second provider is a second class, no call-site changes
 * elsewhere in the codebase" — Paynow is that second class, added in this
 * run. Both are live buyer-facing options (not a superseded default), so
 * the seam the rest of the app depends on is this manager, keyed by the
 * provider string the buyer picked at checkout (§6.6: "Payment methods as
 * logo-labelled radio cards"), never a concrete gateway class.
 */
class PaymentGatewayManager
{
    public function for(string $provider): PaymentGateway
    {
        return match ($provider) {
            'pesepay' => app(PesepayGateway::class),
            'paynow' => app(PaynowGateway::class),
            default => throw new InvalidArgumentException("Unknown payment provider: {$provider}"),
        };
    }

    /**
     * @return array<int, string>
     */
    public function availableProviders(): array
    {
        return ['pesepay', 'paynow'];
    }
}
