<?php

namespace App\Services;

use App\Models\Shipper;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * TDD §3.4 module 23: registration is self-service and immediately
 * active — no KYC/stepper, unlike seller onboarding (see
 * App\Services\SellerOnboardingService). Shared by the API controller
 * (App\Http\Controllers\Api\V1\Shipper\OnboardingController) and the
 * dashboard web form so the two surfaces never duplicate this logic.
 */
class ShipperOnboardingService
{
    public function register(User $user, ?string $businessName): Shipper
    {
        return DB::transaction(function () use ($user, $businessName) {
            $shipper = Shipper::create([
                'user_id' => $user->id,
                'business_name' => $businessName,
                'status' => 'active',
            ]);

            $user->assignRole('shipper');

            return $shipper;
        });
    }
}
