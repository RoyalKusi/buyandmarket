<?php

namespace App\Http\Controllers\Api\V1\Shipper;

use App\Http\Controllers\Controller;
use App\Models\Shipper;
use App\Services\ShipperOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TDD §3.4 module 23: shippers are a distinct role. Onboarding here is
 * deliberately minimal (registration only, immediately active) — a
 * verification/KYC step matching the seller onboarding flow (§3.1 modules
 * 4-5) is flagged as a Run 1.7+ follow-up (dashboards), not silently
 * skipped: see CHANGELOG.md.
 */
class OnboardingController extends Controller
{
    public function register(Request $request, ShipperOnboardingService $onboardingService): JsonResponse
    {
        $this->authorize('register', Shipper::class);

        $data = $request->validate([
            'business_name' => ['nullable', 'string', 'max:255'],
        ]);

        $shipper = $onboardingService->register($request->user(), $data['business_name'] ?? null);

        return response()->json(['data' => $shipper], 201);
    }
}
