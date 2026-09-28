<?php

namespace App\Services;

use App\Models\Seller;
use App\Models\SellerBadge;

/**
 * TDD §3.1 module 6: "Badge rules are computable, not manually assigned:
 * Verified (KYC approved), Top Rated (>=4.5 avg over >=20 reviews), New
 * (<90 days), Fast Responder (<2h median first-response time) —
 * recalculated nightly by a scheduled job."
 *
 * Top Rated and Fast Responder are not computed here yet — they depend on
 * the reviews module (Run 1.5/1.7's §3.5) and buyer-seller messaging
 * (module 37/AI conversations), neither of which exists yet. Wiring them
 * up now would mean inventing a fake signal; this service computes
 * exactly the two badges the current schema can support honestly, and
 * recompute() is where the other two get added once their data exists.
 */
class SellerBadgeService
{
    private const NEW_SELLER_WINDOW_DAYS = 90;

    public function recompute(Seller $seller): void
    {
        $qualifies = [
            'verified' => $seller->kyc_status === 'approved',
            'new_seller' => $seller->created_at->diffInDays(now()) < self::NEW_SELLER_WINDOW_DAYS,
        ];

        foreach ($qualifies as $badge => $shouldHold) {
            if ($shouldHold) {
                SellerBadge::firstOrCreate(
                    ['seller_id' => $seller->id, 'badge' => $badge],
                    ['awarded_at' => now()],
                );
            } else {
                SellerBadge::where('seller_id', $seller->id)->where('badge', $badge)->delete();
            }
        }
    }
}
