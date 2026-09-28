<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\DeliveryRateCard;
use App\Models\DeliveryZone;
use App\Models\OrderGroup;
use App\Models\Product;
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
        ]);
    }

    public function products(): View
    {
        return view('dashboard.seller.products', ['products' => Product::query()->latest()->paginate(15)]);
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
