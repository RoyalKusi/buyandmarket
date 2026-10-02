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

    /**
     * @return HasMany<SponsoredCampaign, $this>
     */
    public function sponsoredCampaigns(): HasMany
    {
        return $this->hasMany(SponsoredCampaign::class);
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

    /**
     * TDD §3.1 module 6 "Top Rated (>=4.5 avg over >=20 reviews)" —
     * aggregated across every one of this seller's products, computed
     * fresh rather than cached (App\Services\SellerBadgeService calls
     * this on its own recompute schedule, same as the other badges).
     *
     * @return array{average: float, count: int}
     */
    public function reviewStats(): array
    {
        $stats = Review::published()
            ->whereHas('product', fn ($q) => $q->whereHas('store', fn ($q) => $q->where('seller_id', $this->id)))
            ->selectRaw('AVG(rating) as average, COUNT(*) as count')
            ->first();

        return [
            'average' => (float) ($stats->average ?? 0),
            'count' => (int) ($stats->count ?? 0),
        ];
    }
}
