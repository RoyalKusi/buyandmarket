<?php

namespace App\Models\Scopes;

use App\Models\Seller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * TDD §6.4 rule 4 / §8.5: "every seller-owned table carries seller_id/
 * store_id and is queried through a global Eloquent scope so a missing
 * WHERE clause in application code cannot leak cross-seller data."
 *
 * This scope is deliberately NOT registered unconditionally on Product —
 * doing so would also hide every other seller's products from buyers and
 * admins, which is not what §8.5 is protecting against (see docs/adr/0002).
 * It is applied only for the duration of a seller-dashboard request, by
 * App\Http\Middleware\ScopeQueriesToActingSeller, so a controller/service
 * method on that surface that forgets an explicit ownership filter still
 * cannot reach another seller's rows. Each seller-owned model states its
 * own ownership path via the SellerOwned interface, since it differs
 * per table (Product goes through store_id; a future table might carry
 * seller_id directly).
 */
class SellerOwnershipScope implements Scope
{
    public function __construct(private readonly Seller $seller) {}

    public function apply(Builder $builder, Model $model): void
    {
        // Each seller-owned model defines its own scopeOwnedBySeller()
        // local scope (App\Models\Concerns\SellerOwned); Eloquent forwards
        // this call to it automatically. Eloquent's own Scope interface
        // erases $builder's model type to the bare Builder, so static
        // analysis has no way to know a local scope method exists here —
        // this is a structural limitation of the interface Laravel
        // itself defines, not something a type hint on this method can
        // fix.
        // @phpstan-ignore method.notFound
        $builder->ownedBySeller($this->seller);
    }
}
