<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Services\SellerOnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * TDD §3.1 module 4 "become a seller" web flow — deferred since Run 1.7
 * (fully functional via the API, this adds the web presentation only).
 * Every step reuses SellerOnboardingService, the same service
 * App\Http\Controllers\Api\V1\Seller\OnboardingController calls, so no
 * business logic is duplicated between the two surfaces.
 */
class SellerOnboardingController extends Controller
{
    public function show(Request $request): View
    {
        return view('dashboard.become-seller', [
            'seller' => $request->user()->seller?->load('onboardingSteps', 'store', 'payoutDetail', 'kycDocuments'),
        ]);
    }

    public function register(Request $request, SellerOnboardingService $onboardingService): RedirectResponse
    {
        $this->authorize('register', Seller::class);

        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
        ]);

        $onboardingService->register($request->user(), $data['business_name']);

        return redirect()->route('dashboard.become-seller')
            ->with('status', 'Seller account created — complete the steps below to start listing products.');
    }

    public function submitKycDocuments(Request $request, SellerOnboardingService $onboardingService): RedirectResponse
    {
        $seller = $this->actingSeller($request);

        $data = $request->validate([
            'national_id' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'proof_of_address' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'business_registration' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);

        $onboardingService->submitKycDocuments($seller, $data);

        return redirect()->route('dashboard.become-seller')->with('status', 'Documents uploaded.');
    }

    public function submitPayoutDetails(Request $request, SellerOnboardingService $onboardingService): RedirectResponse
    {
        $seller = $this->actingSeller($request);

        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:255'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:255'],
        ]);

        $onboardingService->submitPayoutDetails($seller, $data['bank_name'], $data['account_name'], $data['account_number']);

        return redirect()->route('dashboard.become-seller')->with('status', 'Payout details saved.');
    }

    public function createStore(Request $request, SellerOnboardingService $onboardingService): RedirectResponse
    {
        $seller = $this->actingSeller($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:stores,slug'],
        ]);

        $onboardingService->createStore($seller, $data['name'], $data['slug']);

        return redirect()->route('dashboard.become-seller')->with('status', 'Store created.');
    }

    public function submitForReview(Request $request, SellerOnboardingService $onboardingService): RedirectResponse
    {
        $seller = $this->actingSeller($request);

        $onboardingService->submitForReview($seller);

        return redirect()->route('dashboard.become-seller')
            ->with('status', 'Submitted for admin review — you will see the decision here once reviewed.');
    }

    private function actingSeller(Request $request): Seller
    {
        $seller = $request->user()->seller;

        abort_unless($seller !== null, 404);
        $this->authorize('manage', $seller);

        return $seller;
    }
}
