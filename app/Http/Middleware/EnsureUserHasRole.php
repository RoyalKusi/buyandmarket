<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TDD §8.1: a thin role gate for whole route groups (the admin dashboard
 * shell) where a Policy per-resource check isn't the right granularity —
 * individual privileged actions within the group still go through their
 * own Policy (KycReviewController, ProductModerationController, etc.) via
 * the Gate::before admin bypass (App\Providers\AppServiceProvider).
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless($request->user()?->hasAnyRole(...$roles), 403);

        return $next($request);
    }
}
