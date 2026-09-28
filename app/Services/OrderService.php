<?php

namespace App\Services;

use App\Models\CheckoutSession;
use App\Models\Order;
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
                    $this->inventoryService->adjustStock($item->variant, -$item->quantity, 'sale', $orderItem->id);
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
    }

    /**
     * Reverses stock reservation for an order that never completed
     * payment (TDD §5.9: checkout_sessions retain state through
     * PaymentFailed, but an abandoned order's reserved stock must not be
     * held indefinitely).
     */
    public function cancelUnpaidOrder(Order $order): void
    {
        if ($order->status !== 'pending') {
            return;
        }

        DB::transaction(function () use ($order) {
            foreach ($order->orderGroups as $orderGroup) {
                foreach ($orderGroup->items as $item) {
                    $this->inventoryService->adjustStock($item->variant, $item->quantity, 'order_cancelled', $item->id);
                }
            }

            $order->update(['status' => 'cancelled']);
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
