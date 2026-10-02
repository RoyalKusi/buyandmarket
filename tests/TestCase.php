<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // CI's Tests job (.github/workflows/ci.yml) never runs `npm run
        // build` — that's the separate, parallel "Build front-end
        // assets" job — so public/build/manifest.json never exists when
        // the suite runs there. Every view using @vite would otherwise
        // throw ViteManifestNotFoundException the moment a test renders
        // it. This was masked locally in development because a manifest
        // from a prior manual `npm run build` was already sitting on
        // disk, but was never actually exercised against a clean
        // checkout — exactly what CI always starts from.
        $this->withoutVite();
    }

    /**
     * Mobile-app audit finding: the guest-accessible API controllers
     * (cart, checkout, AI conversations) resolve the acting user via
     * $request->user('sanctum') — the only guard that correctly covers
     * both a stateful web session AND a real Bearer-token client — not
     * the bare $request->user() every other authenticated route relies
     * on. Plain actingAs($user) only authenticates the default 'web'
     * guard, so it silently never exercised that sanctum path; every
     * test that looked like it covered an authenticated buyer hitting
     * those endpoints was actually hitting them as a guest. Defaulting
     * to both guards here — only when no explicit guard is requested —
     * makes actingAs() mean what every call site already assumed it
     * meant, without having to touch every one of them individually.
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        if ($guard !== null) {
            return parent::actingAs($user, $guard);
        }

        // AuthManager::shouldUse(null) resolves to whatever
        // config('auth.defaults.guard') currently holds — but
        // setDefaultDriver() mutates that same config value, so
        // authenticating 'sanctum' first poisons a later null-guard
        // call into staying on 'sanctum' instead of falling back to
        // 'web'. Naming 'web' explicitly, last, avoids relying on that
        // mutated fallback.
        parent::actingAs($user, 'sanctum');

        return parent::actingAs($user, 'web');
    }
}
