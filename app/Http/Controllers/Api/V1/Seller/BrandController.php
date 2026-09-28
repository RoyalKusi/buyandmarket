<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\BrandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function store(Request $request, BrandService $brandService): JsonResponse
    {
        $this->authorize('create', Brand::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:brands,slug'],
        ]);

        $brand = $brandService->suggest($request->user()->seller, $data['name'], $data['slug']);

        return response()->json(['data' => $brand], 201);
    }
}
