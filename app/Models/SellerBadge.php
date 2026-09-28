<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TDD §3.1 module 6: a row's presence IS the badge; recomputed nightly by
 * App\Services\SellerBadgeService, never assigned manually.
 */
class SellerBadge extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'seller_id',
        'badge',
        'awarded_at',
    ];

    protected function casts(): array
    {
        return [
            'awarded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }
}
