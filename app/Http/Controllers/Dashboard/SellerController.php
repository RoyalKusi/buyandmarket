<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DeliveryRateCard;
use App\Models\DeliveryZone;
use App\Models\OrderGroup;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\SponsoredCampaign;
use App\Services\Ai\ListingAssistant;
use App\Services\BrandService;
use App\Services\ProductImageService;
use App\Services\ProductService;
use App\Services\ShippingService;
use App\Services\SponsoredCampaignService;
use Illuminate\Http\JsonResponse;
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
        $products = Product::query()->withCount('images')->latest()->paginate(15);

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

    /**
     * TDD §3.2 modules 7/10 "category picker, dynamic variant rows" —
     * deferred since Run 1.7 (product creation was API-only). Every
     * leaf category's attributes ship inline as JSON so the variant
     * rows can switch attribute checkboxes per category without a
     * round trip (the catalogue's category/attribute tree is small
     * enough to send whole).
     */
    public function createProduct(Request $request): View|RedirectResponse
    {
        $this->authorize('create', Product::class);

        if ($request->user()->seller->store === null) {
            return redirect()->route('dashboard.become-seller')
                ->with('status', 'Finish setting up your store before creating a product.');
        }

        $leafCategories = Category::query()
            ->whereDoesntHave('children')
            ->with('attributes.values')
            ->orderBy('name')
            ->get();

        return view('dashboard.seller.products-create', [
            'categories' => $leafCategories,
            'brands' => Brand::where('status', 'approved')->orderBy('name')->get(),
            'categoryAttributesJson' => $leafCategories->mapWithKeys(fn (Category $category) => [
                $category->id => $category->attributes->map(fn ($attribute) => [
                    'id' => $attribute->id,
                    'name' => $attribute->name,
                    'required' => (bool) $attribute->pivot->required,
                    'values' => $attribute->values->map(fn ($value) => ['id' => $value->id, 'value' => $value->value]),
                ]),
            ])->toJson(),
        ]);
    }

    /**
     * TDD §5.6 text-seeded category/attribute suggestion (docs/adr/0007)
     * — a preview only, returned as JSON for the create-product page's
     * Alpine component to apply to the still-unsaved form, never
     * persisted or audited here (see ListingAssistant::
     * suggestCategorization()'s own docblock for why).
     */
    public function suggestCategorization(Request $request, ListingAssistant $assistant): JsonResponse
    {
        $this->authorize('create', Product::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'bullets' => ['required', 'string', 'max:1000'],
        ]);

        $leafCategories = Category::query()->whereDoesntHave('children')->with('attributes.values')->get();
        $suggestion = $assistant->suggestCategorization($data['title'], $data['bullets'], $leafCategories);

        return response()->json([
            'category' => $suggestion['category'] ? ['id' => $suggestion['category']->id, 'name' => $suggestion['category']->name] : null,
            'attributes' => $suggestion['attributes'],
        ]);
    }

    /**
     * TDD §3.2 module 11 "suggest a new brand" — deferred since Run 1.7
     * (fully functional via the API, `POST /api/v1/seller/brands`; web
     * presentation only missing). Submitted inline from the product-
     * creation page rather than a separate screen, since that's the one
     * moment a seller actually discovers their brand isn't listed.
     */
    public function suggestBrand(Request $request, BrandService $brandService): RedirectResponse
    {
        $this->authorize('create', Brand::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:brands,slug'],
        ]);

        $brandService->suggest($request->user()->seller, $data['name'], $data['slug']);

        return redirect()->route('seller.dashboard.products.create')
            ->with('status', 'Brand suggested — it will appear in the dropdown once an admin approves it.');
    }

    public function storeProduct(Request $request, ProductService $productService): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'decimal:0,2', 'numeric', 'min:0'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.sku' => ['required', 'string', 'max:100', 'distinct', 'unique:product_variants,sku'],
            'variants.*.price_override' => ['nullable', 'decimal:0,2', 'numeric', 'min:0'],
            'variants.*.stock_quantity' => ['required', 'integer', 'min:0'],
            'variants.*.attribute_value_ids' => ['sometimes', 'array'],
            'variants.*.attribute_value_ids.*' => ['integer', 'exists:attribute_values,id'],
        ]);

        $productService->create(
            $request->user()->seller,
            collect($data)->except('variants')->all(),
            $data['variants'],
        );

        return redirect()->route('seller.dashboard.products')
            ->with('status', 'Product created as a draft — submit it for review when ready.');
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

    /**
     * TDD §3.2 module 7 / §5.8: the product-image pipeline's dashboard
     * presentation — deferred whole since Run 1.2, added in Run 1.15.
     */
    public function manageImages(Request $request, Product $product): View
    {
        $this->authorize('manageImages', $product);

        return view('dashboard.seller.product-images', [
            'product' => $product->load('images'),
        ]);
    }

    public function storeImage(Request $request, Product $product, ProductImageService $imageService): RedirectResponse
    {
        $this->authorize('manageImages', $product);

        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $image = $imageService->upload($product, $data['image'], $request->user());

        return back()->with('status', $image->status === 'processed'
            ? 'Image uploaded.'
            : "Image rejected: {$image->rejection_reason}");
    }

    public function destroyImage(Request $request, Product $product, ProductImage $image, ProductImageService $imageService): RedirectResponse
    {
        $this->authorize('manageImages', $product);
        abort_unless($image->product_id === $product->id, 404);

        $imageService->destroy($image, $request->user());

        return back()->with('status', 'Image removed.');
    }

    public function makeImagePrimary(Product $product, ProductImage $image, ProductImageService $imageService): RedirectResponse
    {
        $this->authorize('manageImages', $product);
        abort_unless($image->product_id === $product->id, 404);

        $imageService->makePrimary($image);

        return back()->with('status', 'Primary image updated.');
    }

    public function generateImageAltText(Product $product, ProductImage $image, ListingAssistant $assistant): RedirectResponse
    {
        $this->authorize('manageImages', $product);
        abort_unless($image->product_id === $product->id, 404);

        $image->update(['alt_text' => $assistant->suggestAltText($product)]);

        return back()->with('status', 'Alt text generated.');
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

    /**
     * TDD module 15: sponsored placements — deferred since Run 1.4
     * (named alongside the homepage's sponsored block). Seller-
     * submitted, admin-approved; see App\Services\SponsoredCampaignService.
     */
    public function sponsoredCampaigns(Request $request): View
    {
        return view('dashboard.seller.sponsored-campaigns', [
            'campaigns' => $request->user()->seller->sponsoredCampaigns()->with('product')->latest()->get(),
            'products' => Product::query()->where('status', 'published')->get(),
        ]);
    }

    public function storeSponsoredCampaign(Request $request, SponsoredCampaignService $campaignService): RedirectResponse
    {
        $this->authorize('create', SponsoredCampaign::class);

        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'daily_budget' => ['required', 'numeric', 'min:1'],
            'starts_at' => ['required', 'date', 'after_or_equal:today'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $campaignService->create($request->user()->seller, $product, $data['daily_budget'], $data['starts_at'], $data['ends_at'] ?? null);

        return back()->with('status', 'Campaign submitted for admin approval.');
    }
}
