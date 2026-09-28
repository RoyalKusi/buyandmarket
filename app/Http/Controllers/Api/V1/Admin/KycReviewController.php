<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Services\KycReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KycReviewController extends Controller
{
    public function approve(Request $request, Seller $seller, KycReviewService $kycReviewService): JsonResponse
    {
        $this->authorize('review', $seller);

        return response()->json(['data' => $kycReviewService->approve($seller, $request->user())]);
    }

    public function reject(Request $request, Seller $seller, KycReviewService $kycReviewService): JsonResponse
    {
        $this->authorize('review', $seller);

        $data = $request->validate([
            'reason_code' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string'],
        ]);

        return response()->json([
            'data' => $kycReviewService->reject($seller, $request->user(), $data['reason_code'], $data['note'] ?? null),
        ]);
    }
}
