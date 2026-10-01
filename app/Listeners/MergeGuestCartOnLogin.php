<?php

namespace App\Listeners;

use App\Models\Cart;
use App\Services\CartService;
use Illuminate\Auth\Events\Login;

/**
 * TDD §3.4 module 17: "merged on login." App\Services\CartService::
 * mergeIntoUserCart() existed since Run 1.5 but nothing ever called it
 * (Run 1.12 fix) — a guest who added items, then signed in, silently
 * lost their guest cart to an empty or pre-existing account cart.
 */
class MergeGuestCartOnLogin
{
    public function __construct(private readonly CartService $cartService) {}

    public function handle(Login $event): void
    {
        // Laravel's SessionGuard::login() calls $session->migrate(true)
        // (session-fixation protection) *before* firing this event — by
        // the time we're here, session()->getId() is already the new,
        // post-login id, no longer the one the guest cart was saved
        // under. migrate() carries the session's existing data forward
        // under the new id, so the pre-login id stashed by
        // StashSessionIdBeforeLogin (on the earlier Attempting event)
        // survives the swap and is what we actually need to look up by.
        $sessionId = session()->pull('_pre_login_cart_session_id') ?? session()->getId();

        $guestCart = Cart::where('session_id', $sessionId)->first();

        if ($guestCart !== null) {
            $this->cartService->mergeIntoUserCart($guestCart, $event->user);
        }
    }
}
