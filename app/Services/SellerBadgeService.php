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
 * Fast Responder is still not computed — it depends on buyer-seller
 * messaging (module 37/AI conversations), which doesn't exist. Top
 * Rated was added in Run 1.17 once the reviews module existed to
 * compute it from honestly.
 */
class SellerBadgeService
{
    private const NEW_SELLER_WINDOW_DAYS = 90;

    private const TOP_RATED_MIN_AVERAGE = 4.5;

    private const TOP_RATED_MIN_REVIEWS = 20;

    public function recompute(Seller $seller): void
    {
        $reviewStats = $seller->reviewStats();

        $qualifies = [
            'verified' => $seller->kyc_status === 'approved',
            'new_seller' => $seller->created_at->diffInDays(now()) < self::NEW_SELLER_WINDOW_DAYS,
            'top_rated' => $reviewStats['average'] >= self::TOP_RATED_MIN_AVERAGE && $reviewStats['count'] >= self::TOP_RATED_MIN_REVIEWS,
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
