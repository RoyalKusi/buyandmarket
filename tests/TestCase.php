<?php

namespace Tests;

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
}
