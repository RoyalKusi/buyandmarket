<?php

namespace App\Http\Controllers\Api\V1\Shipper;

use App\Http\Controllers\Controller;
use App\Models\Shipper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TDD §3.4 module 23: shippers are a distinct role. Onboarding here is
 * deliberately minimal (registration only, immediately active) — a
 * verification/KYC step matching the seller onboarding flow (§3.1 modules
 * 4-5) is flagged as a Run 1.7+ follow-up (dashboards), not silently
 * skipped: see CHANGELOG.md.
 */
class OnboardingController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $this->authorize('register', Shipper::class);

        $data = $request->validate([
            'business_name' => ['nullable', 'string', 'max:255'],
        ]);

        $shipper = DB::transaction(function () use ($request, $data) {
            $shipper = Shipper::create([
                'user_id' => $request->user()->id,
                'business_name' => $data['business_name'] ?? null,
                'status' => 'active',
            ]);

            $request->user()->assignRole('shipper');

            return $shipper;
        });

        return response()->json(['data' => $shipper], 201);
    }
}
