<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * TDD §6.6: "line items grouped by seller ... the primary UI signal
     * that one order may split into multiple shipments."
     *
     * @return Collection<int, EloquentCollection<int, CartItem>>
     */
    public function itemsGroupedByStore(): Collection
    {
        // Eloquent\Collection's own generic template requires its value
        // type to extend Model, so it can never accurately describe "a
        // collection of collections" — groupBy() on an Eloquent\Collection
        // naturally produces one (via `new static`), so this explicitly
        // rewraps the outer level in a plain Support\Collection to match
        // what's actually declared and returned; each inner group stays
        // a real Eloquent\Collection<CartItem>, which is a perfectly
        // valid (if unconstrained) value type for the outer collection.
        return collect(
            $this->items()->with('variant.product.store')->get()
                ->groupBy(fn (CartItem $item) => $item->variant->product->store_id)
                ->all()
        );
    }

    public function subtotal(): string
    {
        return (string) $this->items->sum(fn (CartItem $item) => $item->price_snapshot * $item->quantity);
    }
}
