<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Production Readiness Report condition #2: this sandbox has no egress
 * path to pesepay.com or paynow.co.zw (confirmed: the environment's
 * outbound proxy rejects the CONNECT) and no live sandbox credentials
 * were provided, so PesepayGateway/PaynowGateway could not be verified
 * against a real account from here — same limitation already disclosed
 * in both classes' docblocks. This command is the achievable substitute:
 * a deployment with real network access and real credentials runs it to
 * confirm, before cutover (TDD §14 stage 1.9), that each gateway's
 * config is complete and its base URL is actually reachable. It does
 * not and cannot confirm the request/response field shapes are correct
 * — only a real test transaction against a live sandbox account does
 * that.
 */
class CheckPaymentGatewayConnectivity extends Command
{
    protected $signature = 'payments:check-connectivity';

    protected $description = 'Verify payment gateway configuration and base-URL reachability (not a substitute for a real test transaction)';

    public function handle(): int
    {
        $allOk = $this->checkGateway('pesepay', [
            'integration_key' => config('services.pesepay.integration_key'),
            'encryption_key' => config('services.pesepay.encryption_key'),
        ], config('services.pesepay.base_url'));

        $allOk = $this->checkGateway('paynow', [
            'integration_id' => config('services.paynow.integration_id'),
            'integration_key' => config('services.paynow.integration_key'),
        ], config('services.paynow.base_url')) && $allOk;

        if (! $allOk) {
            $this->warn('One or more gateways are not ready. Fix the issues above before going live.');

            return self::FAILURE;
        }

        $this->info('All configured gateways have complete credentials and a reachable base URL.');
        $this->comment('This does not confirm request/response field shapes — run a real test transaction against each live sandbox account before cutover.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string|null>  $credentials
     */
    private function checkGateway(string $name, array $credentials, ?string $baseUrl): bool
    {
        $missing = array_keys(array_filter($credentials, fn ($value) => blank($value)));

        if ($missing !== []) {
            $this->error("[{$name}] missing config: ".implode(', ', $missing));

            return false;
        }

        if (blank($baseUrl)) {
            $this->error("[{$name}] no base_url configured.");

            return false;
        }

        try {
            // HEAD avoids sending a real (malformed, unsigned) payment
            // payload — this only confirms the host is reachable and
            // responding, not that the integration itself works.
            Http::timeout(10)->head($baseUrl);
        } catch (ConnectionException $e) {
            $this->error("[{$name}] could not reach {$baseUrl}: {$e->getMessage()}");

            return false;
        }

        $this->info("[{$name}] credentials present and {$baseUrl} is reachable.");

        return true;
    }
}
