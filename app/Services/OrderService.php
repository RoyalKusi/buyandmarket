<?php

namespace App\Services;

use App\Models\CheckoutSession;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * TDD §3.4 module 20-21: the order is the buyer-facing purchase record;
 * order splitting decomposes it into one order_groups row per seller.
 */
class OrderService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly CommissionService $commissionService,
        private readonly AuditLogger $auditLogger,
        private readonly AnalyticsService $analyticsService,
        private readonly NotificationMailer $notificationMailer,
    ) {}

    public function createFromCheckoutSession(CheckoutSession $session): Order
    {
        $itemsByStore = $session->cart->itemsGroupedByStore();

        if ($itemsByStore->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Cannot check out an empty cart.']);
        }

        return DB::transaction(function () use ($session, $itemsByStore) {
            $deliverySelection = $session->delivery_selection ?? [];

            $total = 0.0;
            $order = Order::create([
                'user_id' => $session->user_id,
                'guest_email' => $session->guest_email,
                'guest_phone' => $session->guest_phone,
                'order_number' => $this->uniqueOrderNumber(),
                'total' => 0,
                'status' => 'pending',
            ]);

            foreach ($itemsByStore as $storeId => $items) {
                $store = $items->first()->variant->product->store;
                $deliveryFee = (float) ($deliverySelection[$storeId]['fee'] ?? 0);

                // Amounts here are always DECIMAL(12,2)-scale (TDD §6.4
                // rule 5), well within float64's exact-integer range once
                // multiplied by 100 — no bcmath dependency (not guaranteed
                // present on every PHP install, and isn't here).
                $subtotal = round($items->sum(fn ($item) => (float) $item->price_snapshot * $item->quantity), 2);

                $orderGroup = $order->orderGroups()->create([
                    'seller_id' => $store->seller_id,
                    'status' => 'pending',
                    'subtotal' => $subtotal,
                    'delivery_fee' => $deliveryFee,
                ]);

                foreach ($items as $item) {
                    // Audit finding (P1, overselling race): $item->variant
                    // was loaded minutes earlier (checkout can sit through
                    // address/delivery selection before payment), and
                    // Cart::itemsGroupedByStore() never re-validates it.
                    // Re-fetch with a row lock, inside this same
                    // transaction, and re-check stock right before
                    // reserving it — this both closes the stale-read gap
                    // and serializes concurrent buyers racing for the same
                    // last unit (the second transaction blocks on the
                    // lock, then sees the first buyer's decrement).
                    $lockedVariant = ProductVariant::whereKey($item->variant_id)->lockForUpdate()->first();

                    if ($lockedVariant === null || $lockedVariant->stock_quantity < $item->quantity) {
                        $available = $lockedVariant->stock_quantity ?? 0;

                        throw ValidationException::withMessages([
                            'cart' => "Only {$available} of \"{$item->variant->product->title}\" left in stock — please update your cart.",
                        ]);
                    }

                    $orderItem = $orderGroup->items()->create([
                        'variant_id' => $item->variant_id,
                        'quantity' => $item->quantity,
                        // TDD §6.4 rule 1: snapshot at purchase, never a
                        // live join to the product/variant price.
                        'price_at_purchase' => $item->price_snapshot,
                    ]);

                    // Reserve stock at order creation, before payment
                    // confirms, so two buyers can't both check out the
                    // last unit while the first is still paying.
                    $this->inventoryService->adjustStock($lockedVariant, -$item->quantity, 'sale', $orderItem->id);
                }

                $this->commissionService->recordForOrderGroup($orderGroup);

                $total = round($total + $subtotal + $deliveryFee, 2);
            }

            $order->update(['total' => $total]);

            return $order->fresh(['orderGroups.items']);
        });
    }

    /**
     * Called by a PaymentGateway once a payment webhook confirms success
     * (App\Services\Payments\AbstractPaymentGateway::applyWebhookResult).
     */
    public function confirmPaidOrder(Order $order): void
    {
        if ($order->status !== 'pending') {
            return;
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => 'confirmed']);
            $order->orderGroups()->where('status', 'pending')->update(['status' => 'confirmed']);

            $this->auditLogger->log(
                actor: 'system',
                action: 'order.confirmed',
                subject: $order,
                before: ['status' => 'pending'],
                after: ['status' => 'confirmed'],
            );
        });

        // TDD module 41: the analytics event stream's "order_placed"
        // signal — fired on actual payment confirmation (not at
        // checkout-session creation, when the purchase could still fail)
        // so seller sales summaries never count an order that never paid.
        $order->load('orderGroups.items.variant.product', 'user');
        foreach ($order->orderGroups as $orderGroup) {
            foreach ($orderGroup->items as $item) {
                $this->analyticsService->record('order_placed', $item->variant->product, $order->user, [
                    'quantity' => $item->quantity,
                    'revenue' => (string) $item->price_at_purchase,
                ]);
            }
        }

        // Production Readiness Report condition #1: the buyer's durable,
        // off-platform confirmation that payment succeeded.
        $this->notificationMailer->orderConfirmed($order);
    }

    /**
     * Reverses stock reservation for an order that never completed
     * payment (TDD §5.9: checkout_sessions retain state through
     * PaymentFailed, but an abandoned order's reserved stock must not be
     * held indefinitely). Audit finding (P1): this method existed but was
     * never called from anywhere — no scheduled job released stock for
     * abandoned checkouts, so a buyer who started payment and never
     * finished it (closed the tab, a webhook was never delivered) held
     * that stock reserved forever. See App\Console\Commands\
     * CancelAbandonedOrders, scheduled hourly (routes/console.php).
     *
     * Also fixes a TOCTOU race in the pre-fix version: the pending-status
     * check and the cancellation were two separate steps, so two
     * concurrent callers (e.g. a slow webhook arriving at the same moment
     * the scheduled sweep runs) could both pass the check and both
     * restore stock — double-crediting inventory that was only ever
     * reserved once. The status check now happens inside the same
     * transaction as a locked read, and the update is conditioned on
     * still being 'pending' with its affected-row count checked before
     * any stock is touched.
     */
    public function cancelUnpaidOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== 'pending') {
                return;
            }

            $cancelled = Order::whereKey($order->id)->where('status', 'pending')->update(['status' => 'cancelled']);

            if ($cancelled === 0) {
                return;
            }

            foreach ($order->orderGroups as $orderGroup) {
                foreach ($orderGroup->items as $item) {
                    $this->inventoryService->adjustStock($item->variant, $item->quantity, 'order_cancelled', $item->id);
                }
            }

            $order->orderGroups()->update(['status' => 'cancelled']);
        });
    }

    private function uniqueOrderNumber(): string
    {
        do {
            $candidate = 'BM-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
        } while (Order::where('order_number', $candidate)->exists());

        return $candidate;
    }
}
