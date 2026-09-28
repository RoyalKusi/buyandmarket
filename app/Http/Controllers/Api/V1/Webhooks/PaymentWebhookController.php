<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * TDD §7.4 / §8.3: HMAC/signature-verified before any processing;
 * excluded from CSRF (bootstrap/app.php) since the provider, not a
 * browser session, is the caller.
 */
class PaymentWebhookController extends Controller
{
    public function handle(Request $request, string $provider, PaymentGatewayManager $gateways): Response
    {
        $gateway = $gateways->for($provider);

        abort_unless($gateway->verifyWebhookSignature($request), 403, 'Invalid webhook signature.');

        $gateway->handleWebhook($request);

        return response('', 200);
    }
}
