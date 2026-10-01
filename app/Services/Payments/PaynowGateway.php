<?php

namespace App\Services\Payments;

use App\Contracts\PaymentInitiationResult;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Paynow (paynow.co.zw) — the second PaymentGateway implementation added
 * in this run, alongside Pesepay, demonstrating TDD §7.4's "a second
 * provider is a second class, no call-site changes elsewhere in the
 * codebase."
 *
 * Integration shape follows Paynow's publicly documented web-initiated
 * transaction flow: form-encoded POST, request/response integrity via a
 * SHA512 hash of every field's value (in submission order) concatenated
 * with the integration key, uppercase hex. This sandbox has no network
 * path to paynow.co.zw to verify field-for-field against a live account,
 * so treat the exact field set/order here as implementing the documented
 * pattern, not as verified against a live sandbox response — confirm
 * against a real Paynow merchant account before going live (also true of
 * PesepayGateway; TDD §14 stage 1.9's cutover already requires a
 * production-configuration staging run before launch, which is the right
 * place for that verification).
 */
class PaynowGateway extends AbstractPaymentGateway
{
    protected function providerName(): string
    {
        return 'paynow';
    }

    public function initiate(Order $order): PaymentInitiationResult
    {
        $reference = 'BM-'.$order->order_number.'-'.Str::random(6);

        $fields = [
            'id' => config('services.paynow.integration_id'),
            'reference' => $reference,
            'amount' => number_format((float) $order->total, 2, '.', ''),
            'additionalinfo' => "BuyAndMarket order {$order->order_number}",
            'returnurl' => config('services.paynow.return_url') ?: $this->defaultReturnUrl(),
            'resulturl' => config('services.paynow.result_url'),
            'authemail' => $order->user?->email ?? $order->guest_email ?? '',
            'status' => 'Message',
        ];
        $fields['hash'] = $this->hashFields($fields);

        $response = Http::asForm()->post(config('services.paynow.base_url').'/initiatetransaction', $fields);
        $parsed = $this->parseUrlEncodedResponse($response->body());

        $payment = $this->recordInitiatedPayment($order, $reference);

        if (($parsed['status'] ?? null) !== 'Ok') {
            $payment->update(['status' => 'failed', 'raw_payload' => $parsed]);

            return new PaymentInitiationResult($payment, null, $parsed['error'] ?? 'Paynow declined to initiate this payment.');
        }

        return new PaymentInitiationResult($payment, $parsed['browserurl'] ?? null);
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $fields = $request->except('hash');
        $expected = $this->hashFields($fields);

        return hash_equals($expected, (string) $request->input('hash'));
    }

    public function handleWebhook(Request $request): void
    {
        $reference = (string) $request->input('reference');
        $paynowStatus = (string) $request->input('status');

        $status = match (strtolower($paynowStatus)) {
            'paid', 'awaiting delivery', 'delivered' => 'succeeded',
            'cancelled', 'disputed' => 'failed',
            default => 'processing',
        };

        $this->applyWebhookResult($reference, $status, $request->all());
    }

    public function refund(Payment $payment, string $amount): void
    {
        // TDD §7.4: refunds are modelled as their own row (App\Models\
        // Refund) — Paynow itself has no refund API; a refund is
        // reconciled manually against the merchant's Paynow dashboard.
        // This method exists to satisfy the PaymentGateway contract and
        // is where an automated refund call would go if Paynow adds one.
        throw new \RuntimeException('Paynow has no refund API; process refunds manually and record via App\Services\RefundService.');
    }

    public function getStatus(Payment $payment): string
    {
        return $payment->status;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function hashFields(array $fields): string
    {
        $concatenated = implode('', array_map(strval(...), $fields)).config('services.paynow.integration_key');

        return strtoupper(hash('sha512', $concatenated));
    }

    /**
     * @return array<string, string>
     */
    private function parseUrlEncodedResponse(string $body): array
    {
        parse_str($body, $parsed);

        return $parsed;
    }
}
