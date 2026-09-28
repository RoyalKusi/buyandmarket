<?php

namespace App\Http\Middleware;

use App\Models\Product;
use App\Models\ProductVariant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TDD §6.4 rule 4 / §8.5: applies the seller-ownership global scope
 * (App\Models\Scopes\SellerOwnershipScope) for the lifetime of this
 * request, on every seller-dashboard route. A missing ->where() in a
 * seller controller/service still cannot reach another seller's rows.
 *
 * Registered only on the /api/v1/seller/* route group — buyer and admin
 * routes never load this middleware, so full catalogue visibility there
 * is unaffected. See docs/adr/0002 for why this isn't a blanket global
 * scope on the models themselves.
 */
class ScopeQueriesToActingSeller
{
    public function handle(Request $request, Closure $next): Response
    {
        $seller = $request->user()?->seller;

        abort_unless($seller !== null, 403, 'This action requires a seller account.');

        Product::scopeToSeller($seller);
        ProductVariant::scopeToSeller($seller);

        return $next($request);
    }
}
