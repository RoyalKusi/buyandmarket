<?php

namespace App\Models;

use App\Models\Concerns\SellerOwned;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * TDD §8.5's own worked example of seller isolation: "a seller's API
 * token cannot retrieve another seller's order_groups ... even with a
 * guessed ID (404, not 403)."
 */
class OrderGroup extends Model
{
    use HasFactory, SellerOwned;

    protected $fillable = [
        'order_id',
        'seller_id',
        'status',
        'subtotal',
        'commission_amount',
        'delivery_fee',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasOne<Commission, $this>
     */
    public function commission(): HasOne
    {
        return $this->hasOne(Commission::class);
    }

    public function scopeOwnedBySeller(Builder $query, Seller $seller): void
    {
        $query->where('seller_id', $seller->id);
    }
}
