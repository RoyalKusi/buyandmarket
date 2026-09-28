<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Store;
use Illuminate\View\View;

/**
 * Design System §6.1. Sections requiring a module this build hasn't
 * reached yet are omitted rather than faked — see CHANGELOG.md, Run 1.4,
 * for the full list (hero carousel content, "Deals near you"
 * geolocation-ranked rail, AI-curated "Picked for you", sponsored
 * placement block, trust strip).
 */
class HomeController extends Controller
{
    public function index(): View
    {
        return view('storefront.home', [
            'categories' => Category::query()->whereNull('parent_id')->orderBy('name')->limit(12)->get(),
            'stores' => Store::query()
                ->whereHas('seller', fn ($q) => $q->where('status', 'active'))
                ->withCount(['products' => fn ($q) => $q->published()])
                ->orderByDesc('products_count')
                ->limit(8)
                ->get(),
        ]);
    }
}
