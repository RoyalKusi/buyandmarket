<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'suggested_by_seller_id',
    ];

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function suggestedBy(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'suggested_by_seller_id');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
