<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Seller;
use App\Models\SponsoredCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * TDD module 15: seller-submitted, admin-approved sponsored placements.
 * No billing/invoicing is wired to `daily_budget` yet — flagged in
 * CHANGELOG.md, same honest-gap pattern as every other payment-adjacent
 * feature this sandbox has no live gateway to verify against.
 */
class SponsoredCampaignService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function create(Seller $seller, Product $product, string $dailyBudget, string $startsAt, ?string $endsAt): SponsoredCampaign
    {
        if ($product->store->seller_id !== $seller->id) {
            throw ValidationException::withMessages(['product_id' => 'You can only sponsor your own products.']);
        }

        if ($product->status !== 'published') {
            throw ValidationException::withMessages(['product_id' => 'Only a published product can be sponsored.']);
        }

        return SponsoredCampaign::create([
            'seller_id' => $seller->id,
            'product_id' => $product->id,
            'daily_budget' => $dailyBudget,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'pending',
        ]);
    }

    public function approve(SponsoredCampaign $campaign, User $admin): SponsoredCampaign
    {
        return DB::transaction(function () use ($campaign, $admin) {
            $before = $campaign->only(['status']);
            $campaign->update(['status' => 'active']);

            $this->auditLogger->log(
                actor: $admin,
                action: 'sponsored_campaign.approved',
                subject: $campaign,
                before: $before,
                after: $campaign->only(['status']),
            );

            return $campaign;
        });
    }

    public function reject(SponsoredCampaign $campaign, User $admin): SponsoredCampaign
    {
        return DB::transaction(function () use ($campaign, $admin) {
            $before = $campaign->only(['status']);
            $campaign->update(['status' => 'rejected']);

            $this->auditLogger->log(
                actor: $admin,
                action: 'sponsored_campaign.rejected',
                subject: $campaign,
                before: $before,
                after: $campaign->only(['status']),
            );

            return $campaign;
        });
    }

    /**
     * TDD §6.1's homepage sponsored block / search's sponsored slot —
     * only a live, active campaign for a still-published product is
     * ever eligible (re-checked here, not trusted from the campaign's
     * own stale status, same "never trust embedded state" discipline
     * docs/adr/0006 already applies to RAG retrieval).
     *
     * @return Collection<int, SponsoredCampaign>
     */
    public function placementsFor(int $limit = 1): Collection
    {
        return SponsoredCampaign::active()
            ->whereHas('product', fn ($q) => $q->where('status', 'published'))
            ->with('product.store')
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }
}
