<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'slug',
        'name',
        'banner_path',
        'slug_locked_at',
    ];

    protected function casts(): array
    {
        return [
            'slug_locked_at' => 'datetime',
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
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * TDD §3.1 module 3: slug is immutable after 30 days live.
     */
    public function slugIsLocked(): bool
    {
        return $this->slug_locked_at !== null && $this->slug_locked_at->isPast();
    }
}
