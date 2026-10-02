<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only. Only App\Services\ProductService writes here, alongside
 * the price mutation it records (TDD §3.2 module 13).
 */
class PriceHistory extends Model
{
    protected $table = 'price_history';

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'old_price',
        'new_price',
        'actor_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_price' => 'decimal:2',
            'new_price' => 'decimal:2',
            'created_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
