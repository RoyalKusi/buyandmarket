<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Production-audit finding (hardening): no baseline security headers
 * were set anywhere in the app. These four are risk-free additions —
 * none change observable behaviour for a legitimate browser, Livewire,
 * or Alpine. A Content-Security-Policy is deliberately NOT added here:
 * Alpine.js (used throughout the storefront/dashboard — the price
 * slider, search suggestions, 2FA forms, product-creation form) needs
 * 'unsafe-eval' for its expression evaluation unless swapped for its
 * CSP-safe build, and shipping a strict CSP without a real browser QA
 * pass risks silently breaking every Alpine-powered interaction
 * site-wide — flagged as a follow-up requiring that QA pass, not
 * guessed at here.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }
}
