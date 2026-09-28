<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
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
    public function show(Product $product): View
    {
        $this->authorize('view', $product);

        return view('storefront.product', [
            'product' => $product->load(['variants.attributeValues.attribute', 'category', 'brand', 'store.seller']),
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => route('storefront.home')],
                ['label' => $product->category->name, 'href' => route('storefront.categories.show', $product->category)],
                ['label' => $product->title, 'href' => route('storefront.products.show', $product)],
            ],
        ]);
    }
}
