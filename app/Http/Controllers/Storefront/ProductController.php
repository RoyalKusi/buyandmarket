<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ReviewService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Design System §6.4 (PDP). Omitted here (flagged in CHANGELOG.md, Run
 * 1.4): image gallery (no product-images module until Run 1.9), reviews
 * tab content (module 33 not built), related/recently-viewed rails
 * (module 40, needs view-tracking data this run doesn't collect yet), the
 * "Ask about this product" AI entry point (Run 1.8), and working Add to
 * cart / Buy now (Run 1.5 — the buttons render per spec but are disabled
 * with an explanatory label rather than silently doing nothing).
 */
class ProductController extends Controller
{
    public function show(Product $product, ReviewService $reviewService): View
    {
        $this->authorize('view', $product);

        $product->load([
            'variants.attributeValues.attribute',
            'category',
            'brand',
            'store.seller',
            'images' => fn ($query) => $query->where('status', 'processed'),
            'reviews' => fn ($query) => $query->published()->with('user')->latest(),
        ]);

        return view('storefront.product', [
            'product' => $product,
            'reviewStats' => [
                'average' => round((float) $product->reviews->avg('rating'), 1),
                'count' => $product->reviews->count(),
            ],
            'canReview' => Auth::check() && $reviewService->canReview($product, Auth::user()),
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => route('storefront.home')],
                ['label' => $product->category->name, 'href' => route('storefront.categories.show', $product->category)],
                ['label' => $product->title, 'href' => route('storefront.products.show', $product)],
            ],
        ]);
    }
}
