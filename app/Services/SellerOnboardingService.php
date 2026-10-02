<?php

namespace App\Services;

use App\Models\KycDocument;
use App\Models\Seller;
use App\Models\SellerOnboardingStep;
use App\Models\SellerPayoutDetail;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * TDD §3.1 module 4: "Stepper: business info -> KYC documents ->
 * bank/payout details -> store setup -> first product -> admin review;
 * resumable, each step's completion timestamped."
 */
class SellerOnboardingService
{
    private const REQUIRED_KYC_TYPES = ['national_id', 'proof_of_address'];

    public function register(User $user, string $businessName): Seller
    {
        return DB::transaction(function () use ($user, $businessName) {
            $seller = Seller::create([
                'user_id' => $user->id,
                'business_name' => $businessName,
                'status' => 'pending',
                'kyc_status' => 'pending',
            ]);

            foreach (SellerOnboardingStep::STEPS as $step) {
                $seller->onboardingSteps()->create([
                    'step' => $step,
                    'completed_at' => $step === 'business_info' ? now() : null,
                ]);
            }

            // Self-registering as a seller is a different action from the
            // admin-driven "grant this role to that user" endpoint (Run
            // 1.1's RoleAssignmentPolicy) — a user is always free to
            // become a seller of their own account.
            $user->assignRole('seller');

            return $seller;
        });
    }

    /**
     * @param  array<string, UploadedFile>  $documents  Keyed by document type.
     */
    public function submitKycDocuments(Seller $seller, array $documents): Seller
    {
        return DB::transaction(function () use ($seller, $documents) {
            foreach ($documents as $type => $file) {
                $path = $file->store('', 'kyc');

                KycDocument::create([
                    'seller_id' => $seller->id,
                    'type' => $type,
                    'file_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'status' => 'pending',
                    'uploaded_at' => now(),
                ]);
            }

            $submittedTypes = $seller->kycDocuments()->pluck('type')->unique();
            if (collect(self::REQUIRED_KYC_TYPES)->every(fn ($type) => $submittedTypes->contains($type))) {
                $this->completeStep($seller, 'kyc_documents');
            }

            return $seller;
        });
    }

    public function submitPayoutDetails(Seller $seller, string $bankName, string $accountName, string $accountNumber): Seller
    {
        return DB::transaction(function () use ($seller, $bankName, $accountName, $accountNumber) {
            SellerPayoutDetail::updateOrCreate(
                ['seller_id' => $seller->id],
                ['bank_name' => $bankName, 'account_name' => $accountName, 'account_number' => $accountNumber],
            );

            $this->completeStep($seller, 'bank_details');

            return $seller;
        });
    }

    public function createStore(Seller $seller, string $name, string $slug): Store
    {
        if ($seller->store !== null) {
            throw ValidationException::withMessages([
                'store' => 'This seller already has a store (TDD §3.1: one store per seller at launch).',
            ]);
        }

        return DB::transaction(function () use ($seller, $name, $slug) {
            $store = $seller->store()->create([
                'name' => $name,
                'slug' => $slug,
                // TDD §3.1 module 3: "slug is unique, immutable after 30
                // days live." slug_locked_at marks the moment the slug
                // stops being editable, not the moment it started.
                'slug_locked_at' => now()->addDays(30),
            ]);

            $this->completeStep($seller, 'store_setup');

            return $store;
        });
    }

    /**
     * Called by App\Services\ProductService::create() once a seller's
     * first product exists.
     */
    public function markFirstProductStepComplete(Seller $seller): void
    {
        $step = $seller->onboardingSteps()->where('step', 'first_product')->first();

        if ($step !== null && ! $step->isComplete()) {
            $step->update(['completed_at' => now()]);
        }
    }

    /**
     * The final stepper step: the seller asks to be reviewed. Requires
     * every prior step to be complete.
     */
    public function submitForReview(Seller $seller): Seller
    {
        $priorSteps = array_diff(SellerOnboardingStep::STEPS, ['admin_review']);
        $incomplete = $seller->onboardingSteps()
            ->whereIn('step', $priorSteps)
            ->whereNull('completed_at')
            ->pluck('step');

        if ($incomplete->isNotEmpty()) {
            throw ValidationException::withMessages([
                'onboarding' => 'Complete these steps before requesting review: '.$incomplete->implode(', '),
            ]);
        }

        $seller->update(['status' => 'under_review']);

        return $seller;
    }

    private function completeStep(Seller $seller, string $step): void
    {
        $seller->onboardingSteps()->where('step', $step)->update(['completed_at' => now()]);
    }
}
