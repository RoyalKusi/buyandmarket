<?php

namespace App\Console\Commands;

use App\Models\Seller;
use App\Services\SellerBadgeService;
use Illuminate\Console\Command;

/**
 * TDD §3.1 module 6: badges are "recalculated nightly by a scheduled job."
 */
class RecomputeSellerBadges extends Command
{
    protected $signature = 'sellers:recompute-badges';

    protected $description = 'Recompute every seller\'s trust badges';

    public function handle(SellerBadgeService $sellerBadgeService): int
    {
        $count = 0;

        foreach (Seller::query()->cursor() as $seller) {
            $sellerBadgeService->recompute($seller);
            $count++;
        }

        $this->info("Recomputed badges for {$count} seller(s).");

        return self::SUCCESS;
    }
}
