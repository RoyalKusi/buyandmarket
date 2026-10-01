<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TDD §8.2: "MFA required for seller (financial settlement access) and
 * all admin/sub-admin roles." config/fortify.php's own comment already
 * claimed this was "enforced in the two-factor challenge flow" — it
 * wasn't; nothing anywhere checked it. This middleware is that
 * enforcement: a seller/admin without 2FA enabled is redirected to set
 * it up before reaching the dashboard that needs it, rather than being
 * silently allowed in.
 */
class EnsureTwoFactorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null
            && ! $user->hasEnabledTwoFactorAuthentication()
            && ! $request->routeIs('dashboard.security')
        ) {
            return redirect()->route('dashboard.security')
                ->with('status', 'Two-factor authentication is required for this account — set it up below to continue.');
        }

        return $next($request);
    }
}
