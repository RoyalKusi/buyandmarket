<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\RecentlyViewedService;
use App\Services\RecommendationService;
use App\Services\ReviewService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Design System §6.4 (PDP). Omitted here (flagged in CHANGELOG.md): the
 * "Ask about this product" AI entry point (Run 1.8), image gallery (Run
 * 1.15), reviews (Run 1.17), and related/recently-viewed rails (module
 * 40, Run 1.19) are all now wired.
 */
class ProductController extends Controller
{
    public function show(
        Product $product,
        ReviewService $reviewService,
        RecommendationService $recommendationService,
        RecentlyViewedService $recentlyViewedService,
    ): View {
        $this->authorize('view', $product);

        $product->load([
            'variants.attributeValues.attribute',
            'category',
            'brand',
            'store.seller',
            'images' => fn ($query) => $query->where('status', 'processed'),
            'reviews' => fn ($query) => $query->published()->with('user')->latest(),
        ]);

        $recentlyViewed = $recentlyViewedService->recentlyViewed(excluding: $product);
        $recentlyViewedService->record($product);

        return view('storefront.product', [
            'product' => $product,
            'reviewStats' => [
                'average' => round((float) $product->reviews->avg('rating'), 1),
                'count' => $product->reviews->count(),
            ],
            'canReview' => Auth::check() && $reviewService->canReview($product, Auth::user()),
            'relatedProducts' => $recommendationService->relatedTo($product),
            'recentlyViewed' => $recentlyViewed,
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => route('storefront.home')],
                ['label' => $product->category->name, 'href' => route('storefront.categories.show', $product->category)],
                ['label' => $product->title, 'href' => route('storefront.products.show', $product)],
            ],
        ]);
    }
}
