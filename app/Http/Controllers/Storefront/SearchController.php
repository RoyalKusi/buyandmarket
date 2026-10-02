<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design System §6.2. The search-as-you-type suggestion dropdown is
 * deferred (needs its own debounced endpoint and keyboard-nav JS); this
 * ships the results page itself, which is what the exit criterion
 * ("reach a PDP entirely through the built UI") actually needs.
 */
class SearchController extends Controller
{
    public function index(Request $request): View
    {
        return view('storefront.search', [
            'query' => $request->string('q')->toString(),
        ]);
    }
}
