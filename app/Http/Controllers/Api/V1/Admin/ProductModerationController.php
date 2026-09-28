<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductModerationController extends Controller
{
    public function approve(Request $request, Product $product, ProductService $productService): JsonResponse
    {
        $this->authorize('approve', $product);

        return response()->json(['data' => $productService->approve($product, $request->user())]);
    }

    public function reject(Request $request, Product $product, ProductService $productService): JsonResponse
    {
        $this->authorize('reject', $product);

        // TDD §4.2: rejection always carries a mandatory reason code +
        // free-text note.
        $data = $request->validate([
            'reason_code' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string'],
        ]);

        return response()->json([
            'data' => $productService->reject($product, $request->user(), $data['reason_code'], $data['note'] ?? null),
        ]);
    }
}
