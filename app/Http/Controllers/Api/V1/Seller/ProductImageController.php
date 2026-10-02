<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductImageController extends Controller
{
    public function store(Request $request, Product $product, ProductImageService $imageService): JsonResponse
    {
        $this->authorize('manageImages', $product);

        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $image = $imageService->upload($product, $data['image'], $request->user());

        return response()->json(['data' => $image], 201);
    }

    public function destroy(Request $request, Product $product, ProductImage $image, ProductImageService $imageService): JsonResponse
    {
        $this->authorize('manageImages', $product);
        abort_unless($image->product_id === $product->id, 404);

        $imageService->destroy($image, $request->user());

        return response()->json(null, 204);
    }

    public function makePrimary(Request $request, Product $product, ProductImage $image, ProductImageService $imageService): JsonResponse
    {
        $this->authorize('manageImages', $product);
        abort_unless($image->product_id === $product->id, 404);

        $imageService->makePrimary($image);

        return response()->json(['data' => $image->fresh()]);
    }

    public function reorder(Request $request, Product $product, ProductImageService $imageService): JsonResponse
    {
        $this->authorize('manageImages', $product);

        $data = $request->validate([
            'image_ids' => ['required', 'array'],
            'image_ids.*' => ['integer', 'exists:product_images,id'],
        ]);

        $imageService->reorder($product, $data['image_ids']);

        return response()->json(['data' => $product->images()->get()]);
    }
}
