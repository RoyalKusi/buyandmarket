<?php

namespace App\Models\Concerns;

use App\Models\Scopes\SellerOwnershipScope;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 */
trait SellerOwned
{
    /**
     * Restrict a query to rows owned by the given seller. Every seller-
     * owned model must implement this — it is the one place that knows
     * its own path back to seller_id (TDD §6.4 rule 4).
     */
    abstract public function scopeOwnedBySeller(Builder $query, Seller $seller): void;

    /**
     * Register the seller-ownership global scope for the current request
     * (App\Http\Middleware\ScopeQueriesToActingSeller). After this call,
     * every query against this model — including one a developer forgot
     * to explicitly scope — is confined to the given seller's own rows.
     */
    public static function scopeToSeller(Seller $seller): void
    {
        static::addGlobalScope(new SellerOwnershipScope($seller));
    }
}
