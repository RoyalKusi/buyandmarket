<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Seller extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_name',
        'status',
        'kyc_status',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasOne<Store, $this>
     */
    public function store(): HasOne
    {
        return $this->hasOne(Store::class);
    }

    /**
     * @return HasMany<KycDocument, $this>
     */
    public function kycDocuments(): HasMany
    {
        return $this->hasMany(KycDocument::class);
    }

    /**
     * @return HasMany<KycReview, $this>
     */
    public function kycReviews(): HasMany
    {
        return $this->hasMany(KycReview::class);
    }

    /**
     * @return HasOne<SellerPayoutDetail, $this>
     */
    public function payoutDetail(): HasOne
    {
        return $this->hasOne(SellerPayoutDetail::class);
    }

    /**
     * @return HasMany<SellerOnboardingStep, $this>
     */
    public function onboardingSteps(): HasMany
    {
        return $this->hasMany(SellerOnboardingStep::class);
    }

    /**
     * @return HasMany<SellerBadge, $this>
     */
    public function badges(): HasMany
    {
        return $this->hasMany(SellerBadge::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * TDD §3.1 module 4/2: not blocked from onboarding activity
     * (creating draft products, submitting documents) — a stricter test
     * than isActive(), since 'pending'/'under_review'/'approved' sellers
     * are still mid-onboarding but not suspended or terminated.
     */
    public function isInGoodStanding(): bool
    {
        return ! in_array($this->status, ['suspended', 'terminated'], true);
    }

    public function hasBadge(string $badge): bool
    {
        return $this->badges()->where('badge', $badge)->exists();
    }
}
