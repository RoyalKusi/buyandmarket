<?php

namespace App\Providers;

use App\Contracts\Ai\EmbeddingProvider;
use App\Contracts\Ai\LlmProvider;
use App\Contracts\SearchProvider;
use App\Listeners\MergeGuestCartOnLogin;
use App\Listeners\StashSessionIdBeforeLogin;
use App\Models\User;
use App\Services\Ai\OpenAiCompatibleEmbeddingProvider;
use App\Services\Ai\OpenAiCompatibleLlmProvider;
use App\Services\Search\EloquentSearchProvider;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // TDD §2.1/§13 stage 4: the only place that ever changes when the
        // platform moves off MySQL full-text onto Meilisearch/Typesense.
        $this->app->bind(SearchProvider::class, EloquentSearchProvider::class);

        // TDD §5/§8.4: single-implementation-today, swappable-tomorrow
        // boundary — see docs/adr/0006.
        $this->app->bind(LlmProvider::class, OpenAiCompatibleLlmProvider::class);
        $this->app->bind(EmbeddingProvider::class, OpenAiCompatibleEmbeddingProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // TDD §8.1/§8.9: full admin access is granted only via an explicit,
        // audit-logged role_assignments row — once granted, it authorizes
        // every Policy check, so individual Policies don't each need to
        // special-case it. Sub-admins are never covered here: their access
        // stays scoped to the admin_roles permission set on their assignment.
        Gate::before(fn (User $user, string $ability) => $user->hasRole('admin') ? true : null);

        // TDD §3.4 module 17: "merged on login" (Run 1.12 fix — see
        // App\Listeners\MergeGuestCartOnLogin's own doc comment).
        Event::listen(Attempting::class, StashSessionIdBeforeLogin::class);
        Event::listen(Login::class, MergeGuestCartOnLogin::class);

        // Production Readiness Report condition #3: email verification.
        // Laravel 11 has no EventServiceProvider stub (auto-discovery
        // covers most cases, but not this pairing) — without this line,
        // enabling Features::emailVerification() in config/fortify.php
        // adds the verify/resend routes and the `verified` middleware
        // check, but never actually sends the first verification email
        // on registration.
        Event::listen(Registered::class, SendEmailVerificationNotification::class);

        // TDD §8.2: minimum 10 characters, breached-password check via a
        // k-anonymity API (HaveIBeenPwned range query) at registration/reset.
        // The breach check is skipped in automated tests so CI stays
        // deterministic and network-independent.
        Password::defaults(function () {
            $rule = Password::min(10);

            return $this->app->environment('testing') ? $rule : $rule->uncompromised();
        });
    }
}
