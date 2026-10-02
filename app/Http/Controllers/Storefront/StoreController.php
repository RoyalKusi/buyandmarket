<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\View\View;

/**
 * Design System §6.5. The tab bar's Categories/About/Reviews/Policies
 * tabs are deferred — this ships "All Products" (the tab exit criteria
 * actually needs to reach a PDP from a store page) with the others
 * visibly present but inert, never silently missing.
 */
class StoreController extends Controller
{
    public function show(Store $store): View
    {
        return view('storefront.store', [
            'store' => $store->load('seller'),
        ]);
    }
}
