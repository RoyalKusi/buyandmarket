<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function store(Request $request, ProductService $productService): JsonResponse
    {
        $this->authorize('create', Product::class);

        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'decimal:0,2', 'numeric', 'min:0'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.sku' => ['required', 'string', 'max:100', 'distinct', 'unique:product_variants,sku'],
            'variants.*.price_override' => ['nullable', 'decimal:0,2', 'numeric', 'min:0'],
            'variants.*.stock_quantity' => ['required', 'integer', 'min:0'],
            'variants.*.attribute_value_ids' => ['sometimes', 'array'],
            'variants.*.attribute_value_ids.*' => ['integer', 'exists:attribute_values,id'],
        ]);

        $product = $productService->create(
            $request->user()->seller,
            collect($data)->except('variants')->all(),
            $data['variants'],
        );

        return response()->json(['data' => $product], 201);
    }

    public function submitForReview(Request $request, Product $product, ProductService $productService): JsonResponse
    {
        $this->authorize('submitForReview', $product);

        return response()->json(['data' => $productService->submitForReview($product)]);
    }

    public function archive(Request $request, Product $product, ProductService $productService): JsonResponse
    {
        $this->authorize('archive', $product);

        return response()->json(['data' => $productService->archive($product, $request->user())]);
    }

    public function updatePrice(Request $request, Product $product, ProductService $productService): JsonResponse
    {
        $this->authorize('update', $product);

        $data = $request->validate([
            'base_price' => ['required', 'decimal:0,2', 'numeric', 'min:0'],
        ]);

        return response()->json(['data' => $productService->updatePrice($product, $data['base_price'], $request->user())]);
    }
}
