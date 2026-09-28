<?php

namespace App\Models;

use App\Models\Concerns\SellerOwned;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory, SellerOwned;

    protected $fillable = [
        'product_id',
        'sku',
        'price_override',
        'stock_quantity',
    ];

    protected function casts(): array
    {
        return [
            'price_override' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsToMany<AttributeValue, $this>
     */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'variant_attribute_values');
    }

    /**
     * @return HasMany<InventoryLedger, $this>
     */
    public function inventoryLedger(): HasMany
    {
        return $this->hasMany(InventoryLedger::class, 'variant_id');
    }

    /**
     * The effective selling price: the variant's own override, or the
     * parent product's base price (TDD §3.2 module 8).
     */
    public function price(): string
    {
        return (string) ($this->price_override ?? $this->product->base_price);
    }

    public function scopeOwnedBySeller(Builder $query, Seller $seller): void
    {
        $query->whereHas(
            'product',
            fn (Builder $q) => $q->whereHas('store', fn (Builder $q2) => $q2->where('seller_id', $seller->id))
        );
    }
}
