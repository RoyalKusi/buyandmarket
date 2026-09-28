<?php

namespace App\Models;

use App\Models\Concerns\SellerOwned;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryRateCard extends Model
{
    use HasFactory, SellerOwned;

    protected $fillable = [
        'seller_id',
        'zone_id',
        'method',
        'base_fee',
        'free_threshold',
        'eta_min_days',
        'eta_max_days',
        'pickup_address',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'base_fee' => 'decimal:2',
            'free_threshold' => 'decimal:2',
            'enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * @return BelongsTo<DeliveryZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'zone_id');
    }

    public function scopeOwnedBySeller(Builder $query, Seller $seller): void
    {
        $query->where('seller_id', $seller->id);
    }

    /**
     * TDD §3.4 module 26: "free-delivery threshold is a seller-level
     * override evaluated per order group."
     */
    public function feeFor(float $subtotal): float
    {
        if ($this->free_threshold !== null && $subtotal >= (float) $this->free_threshold) {
            return 0.0;
        }

        return (float) $this->base_fee;
    }
}
