<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\DeliveryRateCard;
use App\Models\DeliveryZone;
use App\Models\OrderGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Ai\ListingAssistant;
use App\Services\ProductService;
use App\Services\ShippingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * TDD §3.6 module 28 / Design System §6.11: the seller dashboard —
 * behind 'seller.scope' (routes/web.php), so every query here is already
 * confined to the acting seller's own rows (TDD §6.4 rule 4).
 */
class SellerController extends Controller
{
    public function overview(Request $request): View
    {
        $seller = $request->user()->seller;

        $orderGroups = OrderGroup::query()->get();

        return view('dashboard.seller.overview', [
            'seller' => $seller,
            'revenue' => $orderGroups->whereIn('status', ['completed'])->sum(fn ($g) => (float) $g->subtotal),
            'pendingCommission' => $orderGroups->whereNotIn('status', ['cancelled', 'refunded'])->sum(fn ($g) => (float) $g->commission_amount),
            'statusCounts' => $orderGroups->countBy('status'),
            // TDD §5.6 "inventory alerts": low-stock/reorder-point
            // suggestions from sales velocity — this run has no
            // analytics event stream (module 41) to compute velocity
            // from, so it's a plain stock-quantity threshold instead,
            // deterministic rather than AI-generated (flagged in
            // CHANGELOG.md).
            'lowStockVariants' => ProductVariant::query()
                ->whereHas('product', fn ($q) => $q->where('status', 'published'))
                ->where('stock_quantity', '<=', 5)
                ->with('product')
                ->get(),
        ]);
    }

    public function products(): View
    {
        $products = Product::query()->latest()->paginate(15);

        // TDD §5.6 "pricing insights": compares a seller's price against
        // the category price distribution — computed directly here
        // rather than narrated by the LLM, since a deterministic
        // average/min/max is more trustworthy than an LLM restating
        // arithmetic (flagged in CHANGELOG.md as a simplification from
        // the TDD's "AI-generated advisory range" framing).
        $categoryPriceStats = Product::query()
            ->where('status', 'published')
            ->whereIn('category_id', $products->pluck('category_id')->filter()->unique())
            ->selectRaw('category_id, AVG(base_price) as avg_price, MIN(base_price) as min_price, MAX(base_price) as max_price')
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');

        return view('dashboard.seller.products', ['products' => $products, 'categoryPriceStats' => $categoryPriceStats]);
    }

    public function suggestDescription(Request $request, Product $product, ListingAssistant $assistant): RedirectResponse
    {
        $this->authorize('update', $product);

        $data = $request->validate(['bullets' => ['required', 'string', 'max:1000']]);

        $assistant->suggestDescription($product, $data['bullets'], $request->user());

        return back()->with('status', 'AI description suggestion ready below — review before accepting.');
    }

    public function acceptDescription(Request $request, Product $product, ListingAssistant $assistant): RedirectResponse
    {
        $this->authorize('update', $product);

        $assistant->acceptDescription($product, $request->user());

        return back()->with('status', 'Description updated.');
    }

    public function discardDescription(Request $request, Product $product, ListingAssistant $assistant): RedirectResponse
    {
        $this->authorize('update', $product);

        $assistant->discardDescription($product, $request->user());

        return back()->with('status', 'Suggestion discarded.');
    }

    public function submitProductForReview(Product $product, ProductService $productService): RedirectResponse
    {
        $this->authorize('submitForReview', $product);

        $productService->submitForReview($product);

        return back()->with('status', 'Product submitted for review.');
    }

    public function archiveProduct(Request $request, Product $product, ProductService $productService): RedirectResponse
    {
        $this->authorize('archive', $product);

        $productService->archive($product, $request->user());

        return back()->with('status', 'Product archived.');
    }

    public function orders(): View
    {
        return view('dashboard.seller.orders', [
            'orderGroups' => OrderGroup::query()->with('order', 'items.variant.product', 'shipment')->latest()->paginate(15),
            'zones' => DeliveryZone::whereIn('level', ['area', 'city'])->orderBy('name')->get(),
        ]);
    }

    public function assignShipment(Request $request, OrderGroup $orderGroup, ShippingService $shippingService): RedirectResponse
    {
        $data = $request->validate([
            'zone_id' => ['required', 'exists:delivery_zones,id'],
            'method' => ['required', 'in:standard,express,pickup'],
        ]);

        $zone = DeliveryZone::findOrFail($data['zone_id']);
        $shippingService->rateCardFor($orderGroup, $zone, $data['method']);
        $shippingService->assign($orderGroup, $zone, $data['method']);

        return back()->with('status', 'Shipment assigned — open for a shipper to claim.');
    }

    public function delivery(Request $request): View
    {
        return view('dashboard.seller.delivery', [
            'rateCards' => DeliveryRateCard::with('zone')->get(),
            'zones' => DeliveryZone::orderBy('name')->get(),
        ]);
    }

    public function storeRateCard(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'zone_id' => ['required', 'exists:delivery_zones,id'],
            'method' => ['required', 'in:standard,express,pickup'],
            'base_fee' => ['required', 'numeric', 'min:0'],
            'free_threshold' => ['nullable', 'numeric', 'min:0'],
            'eta_min_days' => ['required', 'integer', 'min:0'],
            'eta_max_days' => ['required', 'integer', 'gte:eta_min_days'],
        ]);

        DeliveryRateCard::updateOrCreate(
            [
                'seller_id' => $request->user()->seller->id,
                'zone_id' => $data['zone_id'],
                'method' => $data['method'],
            ],
            collect($data)->except(['zone_id', 'method'])->merge(['enabled' => true])->all(),
        );

        return back()->with('status', 'Delivery rate saved.');
    }
}
