<?php

namespace App\Providers;

use App\Contracts\SearchProvider;
use App\Models\User;
use App\Services\Search\EloquentSearchProvider;
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
