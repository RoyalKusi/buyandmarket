<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Production-audit finding (hardening, non-exploitable but loose):
    | without this file, Laravel's built-in CORS default applies to every
    | api/* route — allowed_origins: ['*'] (any origin may make
    | unauthenticated requests). supports_credentials stays false so no
    | browser ever attaches a session cookie cross-origin regardless, but
    | a wildcard is still wider than this app needs: only the storefront's
    | own domains (TDD §7.1's SANCTUM_STATEFUL_DOMAINS, the same hosts
    | that may use cookie-based Sanctum auth) are ever a legitimate
    | browser-side caller. Bearer-token clients (mobile, third-party
    | integrations) are unaffected either way — CORS is a browser
    | enforcement mechanism, not server-side authentication.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => (function (): array {
        $domains = array_filter(explode(',', env('SANCTUM_STATEFUL_DOMAINS', '')));

        // Both schemes, since SANCTUM_STATEFUL_DOMAINS is host[:port]
        // only (no scheme) and this app runs over plain HTTP in local
        // development but must be HTTPS-only in production (see
        // config/session.php's 'secure' flag, env-driven the same way).
        return array_values(array_unique(array_merge(
            array_map(fn (string $domain) => "http://{$domain}", $domains),
            array_map(fn (string $domain) => "https://{$domain}", $domains),
        )));
    })(),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
