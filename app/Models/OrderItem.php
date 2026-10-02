<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_group_id',
        'variant_id',
        'quantity',
        'price_at_purchase',
    ];

    protected function casts(): array
    {
        return [
            // TDD §6.4 rule 1: a snapshot, never recomputed from the
            // live product/variant price.
            'price_at_purchase' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<OrderGroup, $this>
     */
    public function orderGroup(): BelongsTo
    {
        return $this->belongsTo(OrderGroup::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
