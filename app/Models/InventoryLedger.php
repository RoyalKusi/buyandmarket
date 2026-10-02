<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only (TDD §6.2/§6.4 rule 2). Only App\Services\InventoryService
 * writes here — never mutate product_variants.stock_quantity directly.
 */
class InventoryLedger extends Model
{
    protected $table = 'inventory_ledger';

    public $timestamps = false;

    protected $fillable = [
        'variant_id',
        'delta',
        'reason_code',
        'order_item_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
