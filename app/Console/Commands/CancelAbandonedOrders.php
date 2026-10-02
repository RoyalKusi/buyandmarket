<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;

/**
 * Production-audit finding (P1): App\Services\OrderService::
 * cancelUnpaidOrder() existed but nothing ever called it — an order
 * whose buyer abandoned payment (closed the tab, or whose webhook was
 * never delivered) stayed 'pending' forever with its stock reserved
 * and unsellable. This sweeps orders old enough that any legitimate
 * payment webhook should already have arrived.
 *
 * The threshold is deliberately longer than the 30-minute checkout-
 * session TTL (config('commerce.checkout_session_ttl_minutes')) —
 * that TTL governs when a buyer can no longer *resume* a checkout
 * session, not when a webhook that's already in flight should be
 * presumed lost. An hour gives a slow/retried webhook delivery room
 * without holding stock hostage indefinitely.
 */
class CancelAbandonedOrders extends Command
{
    protected $signature = 'orders:cancel-abandoned {--minutes=60 : Age threshold in minutes}';

    protected $description = 'Cancel pending orders older than the threshold and release their reserved stock';

    public function handle(OrderService $orderService): int
    {
        $threshold = now()->subMinutes((int) $this->option('minutes'));
        $cancelled = 0;

        foreach (Order::where('status', 'pending')->where('created_at', '<=', $threshold)->cursor() as $order) {
            $orderService->cancelUnpaidOrder($order);
            $cancelled++;
        }

        $this->info("Cancelled {$cancelled} abandoned order(s) and released their reserved stock.");

        return self::SUCCESS;
    }
}
