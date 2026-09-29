<?php

namespace App\Models;

use App\Models\Concerns\SellerOwned;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SellerOwned, SoftDeletes;

    protected $fillable = [
        'store_id',
        'category_id',
        'brand_id',
        'title',
        'slug',
        'description',
        'ai_suggested_description',
        'base_price',
        'status',
        'stock_quantity',
    ];

    /**
     * PDP URLs use the slug (App\Services\ProductService generates it from
     * the title at creation) via explicit {product:slug} route bindings —
     * the API keeps id-based binding, so this is opt-in per route, not a
     * global override of getRouteKeyName().
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * @return HasMany<PriceHistory, $this>
     */
    public function priceHistory(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }

    public function scopeOwnedBySeller(Builder $query, Seller $seller): void
    {
        $query->whereHas('store', fn (Builder $q) => $q->where('seller_id', $seller->id));
    }
}
