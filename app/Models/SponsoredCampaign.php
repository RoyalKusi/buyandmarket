<?php

namespace App\Models;

use App\Models\Concerns\SellerOwned;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SponsoredCampaign extends Model
{
    use HasFactory, SellerOwned;

    protected $fillable = [
        'seller_id',
        'product_id',
        'daily_budget',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'daily_budget' => 'decimal:2',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active')
            ->whereDate('starts_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', now()));
    }

    public function scopeOwnedBySeller(Builder $query, Seller $seller): void
    {
        $query->where('seller_id', $seller->id);
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
