<?php

namespace Tests\Feature\Commerce;

use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding (P1): App\Services\OrderService::cancelUnpaidOrder()
 * existed but was never called from anywhere — abandoned pending
 * orders held their reserved stock forever. Fixed with a scheduled
 * command (orders:cancel-abandoned, hourly) and a TOCTOU race fix in
 * cancelUnpaidOrder() itself (locked read + conditional update before
 * any stock is restored).
 */
class CancelAbandonedOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function pendingOrderReservingStock(ProductVariant $variant, int $quantity, Carbon $createdAt): Order
    {
        $order = Order::factory()->create(['status' => 'pending', 'created_at' => $createdAt]);
        $orderGroup = OrderGroup::factory()->for($order)->for($variant->product->store->seller)->create(['status' => 'pending']);
        OrderItem::factory()->for($orderGroup)->for($variant, 'variant')->create(['quantity' => $quantity]);
        $variant->decrement('stock_quantity', $quantity);

        return $order;
    }

    public function test_an_old_pending_order_is_cancelled_and_its_stock_released(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 5]);

        $order = $this->pendingOrderReservingStock($variant, 2, now()->subHours(2));
        $this->assertSame(3, $variant->fresh()->stock_quantity);

        $this->artisan('orders:cancel-abandoned')->assertSuccessful();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(5, $variant->fresh()->stock_quantity, 'Reserved stock was not released back.');
    }

    public function test_a_recent_pending_order_is_left_alone(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 5]);

        $order = $this->pendingOrderReservingStock($variant, 2, now()->subMinutes(5));

        $this->artisan('orders:cancel-abandoned')->assertSuccessful();

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(3, $variant->fresh()->stock_quantity, 'Stock for an in-flight order must not be touched.');
    }

    public function test_a_confirmed_order_is_never_cancelled_by_the_sweep(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 3]);

        $order = $this->pendingOrderReservingStock($variant, 2, now()->subHours(3));
        $order->update(['status' => 'confirmed']);
        $order->orderGroups()->update(['status' => 'confirmed']);

        $this->artisan('orders:cancel-abandoned')->assertSuccessful();

        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame(1, $variant->fresh()->stock_quantity, 'A paid order\'s stock must never be released.');
    }

    public function test_cancelling_an_already_cancelled_order_does_not_double_restore_stock(): void
    {
        $seller = Seller::factory()->active()->create();
        $product = Product::factory()->for($seller->store)->published()->create();
        $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 5]);

        $order = $this->pendingOrderReservingStock($variant, 2, now()->subHours(2));

        $orderService = app(OrderService::class);
        $orderService->cancelUnpaidOrder($order);
        $orderService->cancelUnpaidOrder($order->fresh()); // simulates a second, racing/duplicate call

        $this->assertSame(5, $variant->fresh()->stock_quantity, 'Stock was restored twice for one cancellation.');
    }
}
