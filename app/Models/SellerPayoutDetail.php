<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerPayoutDetail extends Model
{
    protected $fillable = [
        'seller_id',
        'bank_name',
        'account_name',
        'account_number',
    ];

    protected function casts(): array
    {
        return [
            // Sensitive financial data encrypted at rest (TDD §8).
            'account_number' => 'encrypted',
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
