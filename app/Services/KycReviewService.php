<?php

namespace App\Services;

use App\Models\KycReview;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * TDD §3.1 module 5: "reviewed by admin/sub-admin with an audit-logged
 * decision; a seller cannot go active without an approved KYC review."
 *
 * Because "admin review" is the stepper's last step (module 4), every
 * prior step — including store setup and the first product — is already
 * complete by the time a review happens (enforced by
 * SellerOnboardingService::submitForReview()). Approval therefore carries
 * the seller straight from under_review to active in one action, rather
 * than leaving it sitting in an intermediate "approved" state with
 * nothing left to do.
 */
class KycReviewService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function approve(Seller $seller, User $admin): Seller
    {
        $this->assertUnderReview($seller);

        return DB::transaction(function () use ($seller, $admin) {
            KycReview::create([
                'seller_id' => $seller->id,
                'reviewer_id' => $admin->id,
                'decision' => 'approved',
                'created_at' => now(),
            ]);

            $before = $seller->only(['status', 'kyc_status']);
            $seller->update(['kyc_status' => 'approved', 'status' => 'active']);

            $seller->kycDocuments()->where('status', 'pending')->update(['status' => 'approved']);
            $seller->onboardingSteps()->where('step', 'admin_review')->update(['completed_at' => now()]);

            $this->auditLogger->log(
                actor: $admin,
                action: 'seller.kyc_approved',
                subject: $seller,
                before: $before,
                after: $seller->only(['status', 'kyc_status']),
            );

            return $seller;
        });
    }

    public function reject(Seller $seller, User $admin, string $reasonCode, ?string $note = null): Seller
    {
        $this->assertUnderReview($seller);

        return DB::transaction(function () use ($seller, $admin, $reasonCode, $note) {
            KycReview::create([
                'seller_id' => $seller->id,
                'reviewer_id' => $admin->id,
                'decision' => 'rejected',
                'reason_code' => $reasonCode,
                'note' => $note,
                'created_at' => now(),
            ]);

            $before = $seller->only(['status', 'kyc_status']);
            $seller->update(['kyc_status' => 'rejected', 'status' => 'pending']);

            $seller->kycDocuments()->where('status', 'pending')->update(['status' => 'rejected']);

            // TDD §4.2's "fix and resubmit" path: reopen the KYC-documents
            // step so the seller must submit fresh documents before they
            // can request review again.
            $seller->onboardingSteps()->where('step', 'kyc_documents')->update(['completed_at' => null]);

            $this->auditLogger->log(
                actor: $admin,
                action: 'seller.kyc_rejected',
                subject: $seller,
                before: $before,
                after: [...$seller->only(['status', 'kyc_status']), 'reason_code' => $reasonCode, 'note' => $note],
            );

            return $seller;
        });
    }

    private function assertUnderReview(Seller $seller): void
    {
        if ($seller->status !== 'under_review') {
            throw ValidationException::withMessages([
                'status' => "Seller must be 'under_review' to record a KYC decision (currently '{$seller->status}').",
            ]);
        }
    }
}
