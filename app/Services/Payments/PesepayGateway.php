<?php

namespace App\Services\Payments;

use App\Contracts\PaymentInitiationResult;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Pesepay (pesepay.com) — TDD §7.4's named first PaymentGateway
 * implementation. Pesepay's API encrypts request/response payloads with
 * AES-256-CBC using the integration's encryption key (first 16 bytes as
 * the IV), rather than the plain HMAC-signature style most gateways use —
 * this adapter follows that documented shape.
 *
 * This sandbox has no network path to pesepay.com to verify field names
 * and endpoint paths against a live integration account. Confirm the
 * exact request/response schema against Pesepay's current API
 * documentation and a real sandbox key before production use — flagged
 * the same way in PaynowGateway, and for the same reason (TDD §14 stage
 * 1.9 already requires a production-configuration staging run before
 * cutover, which is the place this gets verified end-to-end).
 */
class PesepayGateway extends AbstractPaymentGateway
{
    protected function providerName(): string
    {
        return 'pesepay';
    }

    public function initiate(Order $order): PaymentInitiationResult
    {
        $reference = 'BM-'.$order->order_number.'-'.Str::random(6);

        $payload = [
            'amountDetails' => [
                'amount' => (float) $order->total,
                'currencyCode' => 'USD',
            ],
            'reasonForPayment' => "BuyAndMarket order {$order->order_number}",
            'resultUrl' => config('services.pesepay.result_url'),
            'returnUrl' => config('services.pesepay.return_url') ?: $this->defaultReturnUrl(),
            'merchantReference' => $reference,
        ];

        $response = Http::withHeaders([
            'Authorization' => config('services.pesepay.integration_key'),
            'Content-Type' => 'application/json',
        ])->post(config('services.pesepay.base_url').'/payments/initiate', [
            'payload' => $this->encrypt($payload),
        ]);

        $payment = $this->recordInitiatedPayment($order, $reference);
        $decrypted = $this->decryptResponse($response);

        if (! ($decrypted['success'] ?? false)) {
            $payment->update(['status' => 'failed', 'raw_payload' => $decrypted]);

            return new PaymentInitiationResult($payment, null, $decrypted['message'] ?? 'Pesepay declined to initiate this payment.');
        }

        return new PaymentInitiationResult($payment, $decrypted['redirectUrl'] ?? null);
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        // Pesepay's result callback carries the encrypted payload itself
        // as the credential — it can only have been produced by a party
        // holding the shared encryption key, so a successful decrypt
        // (valid padding, well-formed JSON) is the verification.
        return $this->tryDecrypt((string) $request->input('payload')) !== null;
    }

    public function handleWebhook(Request $request): void
    {
        $decrypted = $this->tryDecrypt((string) $request->input('payload'));

        if ($decrypted === null) {
            return;
        }

        $reference = (string) ($decrypted['merchantReference'] ?? '');
        $pesepayStatus = (string) ($decrypted['status'] ?? '');

        $status = match (strtoupper($pesepayStatus)) {
            'SUCCESS', 'PAID' => 'succeeded',
            'FAILED', 'CANCELLED' => 'failed',
            default => 'processing',
        };

        $this->applyWebhookResult($reference, $status, $decrypted);
    }

    public function refund(Payment $payment, string $amount): void
    {
        Http::withHeaders(['Authorization' => config('services.pesepay.integration_key')])
            ->post(config('services.pesepay.base_url').'/payments/refund', [
                'payload' => $this->encrypt([
                    'referenceNumber' => $payment->provider_reference,
                    'amount' => (float) $amount,
                ]),
            ]);
    }

    public function getStatus(Payment $payment): string
    {
        return $payment->status;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function encrypt(array $data): string
    {
        $key = config('services.pesepay.encryption_key');
        $iv = substr($key, 0, 16);

        return base64_encode(openssl_encrypt(json_encode($data), 'aes-256-cbc', $key, 0, $iv));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tryDecrypt(string $payload): ?array
    {
        $key = config('services.pesepay.encryption_key');
        $iv = substr($key, 0, 16);

        $decrypted = openssl_decrypt(base64_decode($payload), 'aes-256-cbc', $key, 0, $iv);

        if ($decrypted === false) {
            return null;
        }

        $decoded = json_decode($decrypted, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function decryptResponse(Response $response): array
    {
        $payload = $response->json('payload');

        if (! is_string($payload)) {
            return ['success' => false, 'message' => 'Malformed response from Pesepay.'];
        }

        return $this->tryDecrypt($payload) ?? ['success' => false, 'message' => 'Could not decrypt Pesepay response.'];
    }
}
