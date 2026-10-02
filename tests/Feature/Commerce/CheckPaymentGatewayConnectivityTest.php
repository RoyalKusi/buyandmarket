<?php

namespace Tests\Feature\Commerce;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Production Readiness Report condition #2: live-credential verification
 * against the real Pesepay/Paynow sandboxes is not possible from this
 * environment (no egress to either host, no live credentials provided).
 * This command is the achievable substitute — it checks config
 * completeness and base-URL reachability, so a real deployment can run
 * it before cutover. These tests only cover the command's own logic
 * (missing config, unreachable host, success), via Http::fake() —
 * exactly the same testing boundary already used for the gateways'
 * connection-failure handling in PaymentGatewayConnectionFailureTest.
 */
class CheckPaymentGatewayConnectivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_missing_credentials(): void
    {
        config(['services.pesepay.integration_key' => null]);
        config(['services.pesepay.encryption_key' => null]);
        config(['services.paynow.integration_id' => null]);
        config(['services.paynow.integration_key' => null]);

        $this->artisan('payments:check-connectivity')
            ->assertFailed();
    }

    public function test_it_reports_an_unreachable_gateway(): void
    {
        $this->seedValidCredentials();

        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $this->artisan('payments:check-connectivity')
            ->assertFailed();
    }

    public function test_it_succeeds_when_credentials_are_present_and_hosts_respond(): void
    {
        $this->seedValidCredentials();

        Http::fake(['*' => Http::response('', 200)]);

        $this->artisan('payments:check-connectivity')
            ->assertSuccessful();
    }

    private function seedValidCredentials(): void
    {
        config(['services.pesepay.integration_key' => 'test-key']);
        config(['services.pesepay.encryption_key' => str_repeat('a', 32)]);
        config(['services.pesepay.base_url' => 'https://api.pesepay.com/api/payments-engine/v1']);
        config(['services.paynow.integration_id' => 'test-id']);
        config(['services.paynow.integration_key' => 'test-key']);
        config(['services.paynow.base_url' => 'https://www.paynow.co.zw/interface']);
    }
}
