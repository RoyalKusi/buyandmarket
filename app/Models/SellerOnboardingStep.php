<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerOnboardingStep extends Model
{
    /**
     * TDD §3.1 module 4: order matters — this is the stepper's sequence.
     */
    public const STEPS = [
        'business_info',
        'kyc_documents',
        'bank_details',
        'store_setup',
        'first_product',
        'admin_review',
    ];

    protected $fillable = [
        'seller_id',
        'step',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }
}
