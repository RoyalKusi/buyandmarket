<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Attempting;

/**
 * Paired with App\Listeners\MergeGuestCartOnLogin — see its doc comment.
 * `Attempting` fires before SessionGuard::login() regenerates the
 * session id, so this is the last point the guest's own session id is
 * still readable.
 */
class StashSessionIdBeforeLogin
{
    public function handle(Attempting $event): void
    {
        session()->put('_pre_login_cart_session_id', session()->getId());
    }
}
