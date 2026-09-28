<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\BrandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandModerationController extends Controller
{
    public function approve(Request $request, Brand $brand, BrandService $brandService): JsonResponse
    {
        $this->authorize('approve', $brand);

        return response()->json(['data' => $brandService->approve($brand, $request->user())]);
    }

    public function reject(Request $request, Brand $brand, BrandService $brandService): JsonResponse
    {
        $this->authorize('reject', $brand);

        return response()->json(['data' => $brandService->reject($brand, $request->user())]);
    }
}
