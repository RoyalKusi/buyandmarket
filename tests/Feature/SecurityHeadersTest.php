<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Audit finding (hardening): no baseline security response headers
 * existed anywhere in the app. Fixed via App\Http\Middleware\
 * SecurityHeaders, appended to the global middleware stack
 * (bootstrap/app.php) so it covers both web and API responses.
 */
class SecurityHeadersTest extends TestCase
{
    public function test_responses_carry_baseline_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy');
    }

    public function test_api_responses_also_carry_the_headers(): void
    {
        $response = $this->getJson('/api/v1/categories');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }
}
