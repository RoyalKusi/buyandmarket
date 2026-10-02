<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Category::all()]);
    }

    public function store(Request $request, CategoryService $categoryService): JsonResponse
    {
        $this->authorize('create', Category::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:categories,slug'],
            'parent_id' => ['nullable', 'exists:categories,id'],
        ]);

        $parent = isset($data['parent_id']) ? Category::findOrFail($data['parent_id']) : null;

        $category = $categoryService->create($data['name'], $data['slug'], $parent);

        return response()->json(['data' => $category], 201);
    }
}
