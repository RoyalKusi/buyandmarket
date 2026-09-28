<?php

namespace App\Services\Ai;

use App\Models\DeliveryRateCard;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * TDD §5.4: read tools execute directly; state-changing tools
 * (self::STATE_CHANGING_TOOLS) never execute from here — the caller
 * (AssistantService) intercepts them for the confirm-card flow before a
 * ToolExecutor method ever runs. Every tool here re-derives its own
 * authorization scope from the authenticated $user/$sessionId this
 * class is constructed with, never from an argument the model supplied
 * (closing the impersonation vector §5.4 names explicitly) — that is
 * why get_order_status takes an order id but still 404s on another
 * user's order rather than trusting a user_id argument.
 */
class ToolExecutor
{
    public const STATE_CHANGING_TOOLS = ['add_to_cart'];

    public function __construct(
        private readonly ?User $user,
        private readonly ?string $sessionId,
        private readonly CartService $cartService,
    ) {}

    /**
     * @return array{result: array, citations: list<array{type: string, id: int}>}
     */
    public function execute(string $name, array $arguments): array
    {
        return match ($name) {
            'search_products' => $this->searchProducts($arguments),
            'get_product_details' => $this->getProductDetails($arguments),
            'check_stock' => $this->checkStock($arguments),
            'get_order_status' => $this->getOrderStatus($arguments),
            'get_delivery_estimate' => $this->getDeliveryEstimate($arguments),
            'add_to_cart' => $this->addToCart($arguments),
            default => throw new InvalidArgumentException("Unknown tool: {$name}"),
        };
    }

    private function searchProducts(array $arguments): array
    {
        $query = (string) ($arguments['query'] ?? '');

        $products = Product::query()
            ->published()
            ->whereHas('store.seller', fn ($q) => $q->where('status', 'active'))
            ->where('title', 'like', '%'.$query.'%')
            ->limit(8)
            ->get(['id', 'title', 'base_price', 'slug']);

        return [
            'result' => ['products' => $products->map(fn (Product $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'price' => (float) $p->base_price,
                'slug' => $p->slug,
            ])->all()],
            'citations' => $products->map(fn (Product $p) => ['type' => 'product', 'id' => $p->id])->all(),
        ];
    }

    private function getProductDetails(array $arguments): array
    {
        $product = Product::query()->published()->find($arguments['product_id'] ?? null);

        if ($product === null) {
            return ['result' => ['found' => false], 'citations' => []];
        }

        return [
            'result' => [
                'found' => true,
                'id' => $product->id,
                'title' => $product->title,
                'description' => $product->description,
                'price' => (float) $product->base_price,
                'in_stock' => $product->stock_quantity > 0,
            ],
            'citations' => [['type' => 'product', 'id' => $product->id]],
        ];
    }

    private function checkStock(array $arguments): array
    {
        $variant = ProductVariant::find($arguments['variant_id'] ?? null);

        if ($variant === null) {
            return ['result' => ['found' => false], 'citations' => []];
        }

        return [
            'result' => ['found' => true, 'variant_id' => $variant->id, 'stock_quantity' => $variant->stock_quantity],
            'citations' => [['type' => 'product', 'id' => $variant->product_id]],
        ];
    }

    /**
     * TDD §5.4: "parameterized by the authenticated session's user ID
     * server-side, never by an ID the LLM extracts from conversation
     * text."
     */
    private function getOrderStatus(array $arguments): array
    {
        if ($this->user === null) {
            return ['result' => ['error' => 'Sign in to check an order.'], 'citations' => []];
        }

        $order = Order::where('user_id', $this->user->id)->find($arguments['order_id'] ?? null);

        if ($order === null) {
            return ['result' => ['found' => false], 'citations' => []];
        }

        return [
            'result' => [
                'found' => true,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'groups' => $order->orderGroups->map(fn ($g) => [
                    'status' => $g->status,
                    'shipment_status' => $g->shipment?->status,
                ])->all(),
            ],
            'citations' => [['type' => 'order', 'id' => $order->id]],
        ];
    }

    private function getDeliveryEstimate(array $arguments): array
    {
        $variant = ProductVariant::find($arguments['variant_id'] ?? null);
        $zone = DeliveryZone::find($arguments['zone_id'] ?? null);

        if ($variant === null || $zone === null) {
            return ['result' => ['found' => false], 'citations' => []];
        }

        $sellerId = $variant->product->store->seller_id;
        $rateCard = DeliveryRateCard::where('seller_id', $sellerId)
            ->where('zone_id', $zone->id)
            ->where('enabled', true)
            ->first();

        if ($rateCard === null) {
            return ['result' => ['found' => false, 'reason' => 'This seller does not deliver to that zone.'], 'citations' => []];
        }

        return [
            'result' => [
                'found' => true,
                'eta_min_days' => $rateCard->eta_min_days,
                'eta_max_days' => $rateCard->eta_max_days,
                'fee' => (float) $rateCard->base_fee,
            ],
            'citations' => [['type' => 'product', 'id' => $variant->product_id]],
        ];
    }

    /**
     * Only ever reached via AssistantService::confirmAction() — never as
     * a direct response to the LLM's tool call (§5.4).
     */
    private function addToCart(array $arguments): array
    {
        $variant = ProductVariant::findOrFail($arguments['variant_id']);
        $quantity = max(1, (int) ($arguments['quantity'] ?? 1));

        return DB::transaction(function () use ($variant, $quantity) {
            $cart = $this->cartService->getOrCreateCart($this->user, $this->sessionId);
            $item = $this->cartService->addItem($cart, $variant, $quantity);

            return [
                'result' => ['cart_item_id' => $item->id, 'variant_id' => $variant->id, 'quantity' => $item->quantity],
                'citations' => [['type' => 'product', 'id' => $variant->product_id]],
            ];
        });
    }
}
