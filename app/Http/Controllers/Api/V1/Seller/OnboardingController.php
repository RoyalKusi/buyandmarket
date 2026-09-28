<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Services\SellerOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function register(Request $request, SellerOnboardingService $onboardingService): JsonResponse
    {
        $this->authorize('register', Seller::class);

        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
        ]);

        $seller = $onboardingService->register($request->user(), $data['business_name']);

        return response()->json(['data' => $seller->load('onboardingSteps')], 201);
    }

    public function submitKycDocuments(Request $request, Seller $seller, SellerOnboardingService $onboardingService): JsonResponse
    {
        $this->authorize('manage', $seller);

        $data = $request->validate([
            'national_id' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'proof_of_address' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'business_registration' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);

        $onboardingService->submitKycDocuments($seller, $data);

        return response()->json(['data' => $seller->load('kycDocuments', 'onboardingSteps')]);
    }

    public function submitPayoutDetails(Request $request, Seller $seller, SellerOnboardingService $onboardingService): JsonResponse
    {
        $this->authorize('manage', $seller);

        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:255'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:255'],
        ]);

        $onboardingService->submitPayoutDetails($seller, $data['bank_name'], $data['account_name'], $data['account_number']);

        return response()->json(['data' => $seller->load('onboardingSteps')]);
    }

    public function createStore(Request $request, Seller $seller, SellerOnboardingService $onboardingService): JsonResponse
    {
        $this->authorize('manage', $seller);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:stores,slug'],
        ]);

        $store = $onboardingService->createStore($seller, $data['name'], $data['slug']);

        return response()->json(['data' => $store], 201);
    }

    public function submitForReview(Request $request, Seller $seller, SellerOnboardingService $onboardingService): JsonResponse
    {
        $this->authorize('manage', $seller);

        return response()->json(['data' => $onboardingService->submitForReview($seller)]);
    }
}
